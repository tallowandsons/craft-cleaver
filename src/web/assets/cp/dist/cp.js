(function () {
    function init() {
        const root = document.getElementById("cleaver-utility");
        if (!root) return; // Only run on the utility page

        const form = document.getElementById("cleaver-form");
        const runBtn = document.getElementById("run-chop-btn");
        const modal = document.getElementById("chop-modal");
        const confirmBtn = document.getElementById("confirm-chop");
        const cancelBtn = document.getElementById("cancel-chop");
        const confirmInput = document.getElementById("confirm-environment");
        const summary = document.getElementById("chop-summary");
        const loadingOverlay = document.getElementById("loading-overlay");
        // no percent slider; numeric input only

        // Current environment is rendered in the placeholder and instructions
        const currentEnvironment = confirmInput
            ? confirmInput.getAttribute("placeholder")
            : "";

        if (!form || !runBtn) return;

        // Section checkboxes validation
        function updateRunButton() {
            const checkedSections = form.querySelectorAll(
                'input[name="sections[]"]:checked'
            );
            if (checkedSections.length > 0) {
                runBtn.classList.remove("disabled");
                runBtn.removeAttribute("disabled");
            } else {
                runBtn.classList.add("disabled");
                runBtn.setAttribute("disabled", "");
            }
        }

        form.addEventListener("change", function (e) {
            if (e.target && e.target.name === "sections[]") {
                updateRunButton();
            }
        });

        // No slider; numeric input only

        // Run button click
        runBtn.addEventListener("click", function (e) {
            e.preventDefault();

            if (this.classList.contains("disabled")) {
                return;
            }

            // Build summary
            const formData = new FormData(form);
            const sections = formData.getAll("sections[]");
            const percent = formData.get("percent");
            const statuses = formData.getAll("statuses[]");
            const dryRun = formData.get("dryRun") === "1";

            let summaryHtml = "<ul>";
            summaryHtml +=
                "<li><strong>Sections:</strong> " +
                (sections.join(", ") || "—") +
                "</li>";
            summaryHtml +=
                "<li><strong>Percent to delete:</strong> " + percent + "%</li>";
            summaryHtml +=
                "<li><strong>Statuses:</strong> " +
                (statuses.length ? statuses.join(", ") : "All") +
                "</li>";
            summaryHtml +=
                "<li><strong>Mode:</strong> " +
                (dryRun ? "DRY RUN (simulation)" : "LIVE DELETION") +
                "</li>";
            summaryHtml += "</ul>";

            if (summary) summary.innerHTML = summaryHtml;

            // Show modal
            if (modal) {
                modal.classList.remove("hidden");
                if (confirmInput) confirmInput.focus();
            }
        });

        // Environment confirmation validation
        if (confirmInput && confirmBtn) {
            confirmInput.addEventListener("input", function () {
                if (this.value === currentEnvironment) {
                    confirmBtn.classList.remove("disabled");
                    confirmBtn.removeAttribute("disabled");
                } else {
                    confirmBtn.classList.add("disabled");
                    confirmBtn.setAttribute("disabled", "");
                }
            });
        }

        // Cancel button
        if (cancelBtn && modal && confirmInput && confirmBtn) {
            cancelBtn.addEventListener("click", function () {
                modal.classList.add("hidden");
                confirmInput.value = "";
                confirmBtn.classList.add("disabled");
                confirmBtn.setAttribute("disabled", "");
            });
        }

        // Confirm chop
        if (confirmBtn) {
            confirmBtn.addEventListener("click", function (e) {
                e.preventDefault();

                if (this.classList.contains("disabled")) {
                    return;
                }

                // Hide modal and show loading
                if (modal) modal.classList.add("hidden");
                if (loadingOverlay) loadingOverlay.classList.remove("hidden");

                // Prepare form data
                const formData = new FormData(form);
                if (confirmInput)
                    formData.append("confirmEnvironment", confirmInput.value);

                // Submit via AJAX
                const actionUrl = Craft.getActionUrl("cleaver/utility/chop");
                fetch(actionUrl, {
                    method: "POST",
                    body: formData,
                    headers: {
                        "X-Requested-With": "XMLHttpRequest",
                        Accept: "application/json",
                    },
                })
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (data) {
                        if (loadingOverlay)
                            loadingOverlay.classList.add("hidden");

                        if (data && data.success) {
                            Craft.cp.displayNotice(
                                data.message ||
                                    "Chop operation completed successfully"
                            );
                        } else {
                            Craft.cp.displayError(
                                (data && data.message) ||
                                    "An error occurred during the chop operation"
                            );
                        }

                        // Reset form
                        if (confirmInput && confirmBtn) {
                            confirmInput.value = "";
                            confirmBtn.classList.add("disabled");
                            confirmBtn.setAttribute("disabled", "");
                        }
                    })
                    .catch(function (error) {
                        if (loadingOverlay)
                            loadingOverlay.classList.add("hidden");
                        Craft.cp.displayError("Network error occurred");
                        // eslint-disable-next-line no-console
                        console.error("Cleaver chop error:", error);

                        if (confirmInput && confirmBtn) {
                            confirmInput.value = "";
                            confirmBtn.classList.add("disabled");
                            confirmBtn.setAttribute("disabled", "");
                        }
                    });
            });
        }

        // Close modal on ESC
        document.addEventListener("keydown", function (e) {
            if (
                e.key === "Escape" &&
                modal &&
                !modal.classList.contains("hidden")
            ) {
                if (cancelBtn) cancelBtn.click();
            }
        });

        // Initial state
        updateRunButton();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
