import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Notification from "@typo3/backend/notification.js";
import { Popover } from "bootstrap";

/**
 * Client behaviour for the vc_safety_check dashboard widget.
 *
 * Uses event delegation on the document so the handlers keep working after the
 * widget body is replaced with the new rendered markup following a scan.
 */
class SafetyCheckWidget {
  constructor() {
    //add runCheck click event
    document.addEventListener("click", (event) => {
      const button = event.target.closest('[data-vcsafetycheck-action="run"]');
      if (button === null) {
        return;
      }
      event.preventDefault();
      this.runCheck(button);
    });

    //add vulnerability-popup click logic
    document.addEventListener("click", (event) => {
      const button = event.target.closest('.toggle-security-table');
      if (button === null) {
        return;
      }

      const widget = button.closest(".vcsafetycheck-widget");
      const securityTable = widget.querySelector('.vulnerability-popup');
      securityTable.style.display = securityTable.style.display === 'none' ? '' : 'none';
      if (securityTable.style.display === 'none') {
        this.filterBySeverity(securityTable, null);
      }
    });

    document.addEventListener("click", (event) => {
      const box = event.target.closest('[data-vcsafetycheck-action="filter"]');
      if (box === null) {
        return;
      }

      const widget = box.closest(".vcsafetycheck-widget");
      const securityTable = widget?.querySelector('.vulnerability-popup');
      if (!securityTable) {
        return;
      }

      this.filterBySeverity(securityTable, box.dataset.severity);
      securityTable.style.display = '';
    });

    //add InfoPopup logic
    ["mouseover", "focusin"].forEach((type) => {
      document.addEventListener(type, (event) => {
        const box = event.target.closest("[data-vcsafetycheck-info]");
        if (box === null || Popover.getInstance(box) !== null) {
          return;
        }
        this.createInfoPopover(box).show();
      });
    });
  }

  /**
   * Creates the popover showing the severity info of a summary box.
   */
  createInfoPopover(box) {
    return new Popover(box, {
      content: box.dataset.vcsafetycheckInfo,
      html: true,
      trigger: "hover focus",
      placement: "bottom",
      container: "body",
      customClass: "vcsafetycheck-info-popover",
    });
  }

  /**
   * Shows only the rows matching the given severity. Pass null to show all of them again.
   */
  filterBySeverity(popup, severity) {
    const wanted = typeof severity === "string" ? severity.toUpperCase() : null;

    popup.querySelectorAll(".vcsafetycheck-table tbody tr[data-severity]").forEach((row) => {
      row.hidden = wanted !== null && row.dataset.severity.toUpperCase() !== wanted;
    });
  }

  async runCheck(button) {
    const widget = button.closest(".vcsafetycheck-widget");
    const spinner = button.querySelector(".vcsafetycheck-run-spinner");
    const url = (top.TYPO3 ?? TYPO3).settings.ajaxUrls.vcsafetycheck_run;
    const labels = button.dataset;

    button.disabled = true;
    if (spinner) {
      spinner.hidden = false;
    }

    try {
      const response = await new AjaxRequest(url).post({});
      const data = await response.resolve();

      if (data.success && widget && typeof data.html === "string") {
        widget.outerHTML = data.html;
        Notification.success(
          labels.labelTitle,
          labels.labelSuccess
            .replace("%1$s", data.vulnerabilitiesFound)
            .replace("%2$s", data.packagesScanned)
        );
      } else {
        Notification.error(labels.labelTitle, data.message || labels.labelFailed);
      }
    } catch (error) {
      Notification.error(labels.labelTitle, labels.labelError);
    } finally {
      if (document.body.contains(button)) {
        button.disabled = false;
        if (spinner) {
          spinner.hidden = true;
        }
      }
    }
  }
}

export default new SafetyCheckWidget();
