(() => {
    document.querySelectorAll("[data-dialog-open]").forEach(button => {
        const dialog = document.getElementById(button.dataset.dialogOpen);
        if (!dialog || typeof dialog.showModal !== "function") return;

        button.addEventListener("click", () => dialog.showModal());
        dialog.querySelectorAll("[data-dialog-close]").forEach(closeButton => {
            closeButton.addEventListener("click", () => dialog.close());
        });
        dialog.addEventListener("cancel", event => {
            event.preventDefault();
            dialog.close();
        });
        dialog.addEventListener("keydown", event => {
            if (event.key !== "Escape") return;
            event.preventDefault();
            dialog.close();
        });
        dialog.addEventListener("click", event => {
            if (event.target === dialog) dialog.close();
        });
    });

    document.querySelectorAll(".assigned-detail-form").forEach(form => {
        const status = form.querySelector("[data-completion-status]");
        const field = form.querySelector(".completion-photo-field");
        const photo = form.querySelector("[data-completion-photo]");
        const requiredLabel = form.querySelector("[data-photo-required]");
        if (!status || !field || !photo || !requiredLabel) return;

        const updatePhotoRequirement = () => {
            const required = status.value === "Completed" && field.dataset.hasCompletionPhoto !== "true";
            photo.required = required;
            requiredLabel.hidden = !required;
        };
        status.addEventListener("change", updatePhotoRequirement);
        updatePhotoRequirement();
    });

    document.querySelectorAll("[data-history-back]").forEach(link => {
        link.addEventListener("click", event => {
            if (!document.referrer) return;

            let previousUrl;
            try {
                previousUrl = new URL(document.referrer);
            } catch (error) {
                return;
            }

            if (
                previousUrl.origin !== window.location.origin
                || /\/(?:index|login|logout)\.php$/.test(previousUrl.pathname)
            ) return;
            event.preventDefault();
            window.history.back();
        });
    });

    let confirmDialog;
    let title;
    let message;
    let confirmButton;
    let cancelButton;
    let pendingAction = null;
    let returnFocus = null;

    function close() {
        if (!confirmDialog?.open) return;
        pendingAction = null;
        confirmDialog.close();
        if (returnFocus && returnFocus.isConnected) returnFocus.focus();
    }

    function ensureModal() {
        if (confirmDialog) return;

        confirmDialog = document.createElement("dialog");
        confirmDialog.className = "action-confirm-dialog";
        confirmDialog.setAttribute("aria-labelledby", "actionConfirmTitle");
        confirmDialog.setAttribute("aria-describedby", "actionConfirmMessage");
        confirmDialog.innerHTML = `
            <section class="confirm-modal action-confirm-modal">
            <span class="eyebrow">PLEASE CONFIRM</span>
            <h2 id="actionConfirmTitle"></h2>
            <p id="actionConfirmMessage"></p>
            <div class="modal-actions">
                <button class="btn secondary" type="button" data-confirm-cancel>Cancel</button>
                <button class="btn" type="button" data-confirm-continue>Confirm</button>
            </div>
            </section>
        `;
        document.body.appendChild(confirmDialog);
        title = confirmDialog.querySelector("#actionConfirmTitle");
        message = confirmDialog.querySelector("#actionConfirmMessage");
        confirmButton = confirmDialog.querySelector("[data-confirm-continue]");
        cancelButton = confirmDialog.querySelector("[data-confirm-cancel]");

        cancelButton.addEventListener("click", close);
        confirmButton.addEventListener("click", () => {
            const action = pendingAction;
            close();
            if (action) action();
        });
        confirmDialog.addEventListener("cancel", event => {
            event.preventDefault();
            close();
        });
        confirmDialog.addEventListener("click", event => {
            if (event.target === confirmDialog) close();
        });
        document.addEventListener("keydown", event => {
            if (confirmDialog.open && event.key === "Escape") event.stopImmediatePropagation();
        });
    }

    function open(control, action, confirmTitle, confirmMessage, confirmLabel, cancelLabel = "Cancel") {
        ensureModal();
        pendingAction = action;
        returnFocus = control;
        title.textContent = confirmTitle;
        message.textContent = confirmMessage;
        confirmButton.textContent = confirmLabel;
        cancelButton.textContent = cancelLabel;
        confirmDialog.showModal();
        cancelButton.focus();
    }

    document.querySelectorAll("form[data-confirm-message]").forEach(form => {
        form.addEventListener("submit", event => {
            if (form.dataset.confirmed === "true") {
                delete form.dataset.confirmed;
                return;
            }

            event.preventDefault();
            const submitter = event.submitter;
            const label = submitter ? submitter.textContent.trim() : "Continue";
            open(
                submitter || form,
                () => {
                    form.dataset.confirmed = "true";
                    if (typeof form.requestSubmit === "function") {
                        form.requestSubmit(submitter || undefined);
                    } else {
                        form.submit();
                    }
                },
                form.dataset.confirmTitle || "Confirm this action?",
                form.dataset.confirmMessage,
                form.dataset.confirmLabel || label,
                form.dataset.confirmCancelLabel || "Cancel"
            );
        });
    });

    document.querySelectorAll('a[href$="logout.php"]').forEach(link => {
        link.addEventListener("click", event => {
            event.preventDefault();
            open(
                link,
                () => { window.location.href = link.href; },
                "Log out?",
                "Are you sure you want to log out of CampusFix?",
                "Log out"
            );
        });
    });
})();

window.campusfixShowToast = function (titleText, messageText, iconText = "🔔") {
    let container = document.querySelector(".cf-toast-container");
    if (!container) {
        container = document.createElement("div");
        container.className = "cf-toast-container";
        container.setAttribute("aria-live", "polite");
        document.body.appendChild(container);
    }

    const toast = document.createElement("div");
    toast.className = "cf-toast";
    const icon = document.createElement("div");
    icon.className = "cf-toast-icon";
    icon.textContent = iconText;
    const body = document.createElement("div");
    body.className = "cf-toast-body";
    const title = document.createElement("strong");
    title.textContent = titleText;
    const message = document.createElement("span");
    message.textContent = messageText;
    body.append(title, message);
    toast.append(icon, body);
    container.appendChild(toast);
    window.setTimeout(() => {
        toast.classList.add("fade-out");
        window.setTimeout(() => toast.remove(), 320);
    }, 5000);
};

document.querySelectorAll(".flash-notification").forEach(notification => {
    let toastContainer = document.querySelector(".cf-toast-container");
    if (!toastContainer) {
        toastContainer = document.createElement("div");
        toastContainer.className = "cf-toast-container";
        toastContainer.setAttribute("aria-live", "polite");
        document.body.appendChild(toastContainer);
    }

    const isError = notification.classList.contains("error");
    const toast = document.createElement("div");
    toast.className = `cf-toast ${isError ? "error" : "success"}`;
    toast.setAttribute("role", isError ? "alert" : "status");

    const icon = document.createElement("div");
    icon.className = "cf-toast-icon";
    icon.textContent = isError ? "!" : "✓";

    const body = document.createElement("div");
    body.className = "cf-toast-body";
    const title = document.createElement("strong");
    title.textContent = isError
        ? "Unable to complete action"
        : (notification.dataset.toastTitle || "Success");
    const message = document.createElement("span");
    message.textContent = notification.textContent.trim();
    body.append(title, message);
    toast.append(icon, body);
    toastContainer.appendChild(toast);
    notification.remove();

    window.setTimeout(() => {
        toast.classList.add("fade-out");
        window.setTimeout(() => toast.remove(), 320);
    }, 5000);
});

const photoInput = document.getElementById("photo");
const preview = document.getElementById("photoPreview");

if (photoInput && preview) {
    photoInput.addEventListener("change", function () {
        preview.innerHTML = "";
        const file = this.files[0];
        if (!file) return;

        const allowed = ["image/jpeg", "image/png", "image/webp"];
        const maxSize = 5 * 1024 * 1024;

        if (!allowed.includes(file.type)) {
            alert("Only JPG, PNG, and WEBP images are allowed.");
            this.value = "";
            return;
        }

        if (file.size > maxSize) {
            alert("Photo must be 5 MB or smaller.");
            this.value = "";
            return;
        }

        const img = document.createElement("img");
        img.src = URL.createObjectURL(file);
        img.onload = () => URL.revokeObjectURL(img.src);
        preview.appendChild(img);
    });
}

const deleteModal = document.getElementById("deleteModal");
const deleteId = document.getElementById("deleteRequestId");
const deleteLabel = document.getElementById("deleteRequestLabel");
const closeDeleteModal = document.getElementById("closeDeleteModal");
const cancelDelete = document.getElementById("cancelDelete");
let deleteReturnFocus = null;

document.querySelectorAll("[data-confirm-delete]").forEach(button => {
    button.addEventListener("click", () => {
        if (!deleteModal) return;
        deleteId.value = button.dataset.confirmDelete || "";
        deleteLabel.textContent = `Request #${button.dataset.confirmDelete} – ${button.dataset.title || "Untitled"}`;
        deleteReturnFocus = button;
        deleteModal.hidden = false;
        closeDeleteModal.focus();
    });
});

function hideDeleteModal() {
    if (!deleteModal) return;
    deleteModal.hidden = true;
    if (deleteReturnFocus && deleteReturnFocus.isConnected) deleteReturnFocus.focus();
}
if (closeDeleteModal) closeDeleteModal.addEventListener("click", hideDeleteModal);
if (cancelDelete) cancelDelete.addEventListener("click", hideDeleteModal);
if (deleteModal) deleteModal.addEventListener("click", e => { if (e.target === deleteModal) hideDeleteModal(); });

const requestReviewModal = document.getElementById("requestReviewModal");
if (requestReviewModal) {
    const closeRequestModal = requestReviewModal.querySelector("[data-close-request-modal]");
    const closeRequestReview = () => {
        if (closeRequestModal) window.location.href = closeRequestModal.href;
    };

    requestReviewModal.querySelector(".review-panel").focus();
    requestReviewModal.addEventListener("click", event => {
        if (event.target === requestReviewModal) closeRequestReview();
    });
    document.addEventListener("keydown", event => {
        if (event.key !== "Escape") return;
        if (deleteModal && !deleteModal.hidden) {
            hideDeleteModal();
            return;
        }
        closeRequestReview();
    });
}

document.querySelectorAll(".faq-question").forEach(button => {
    button.addEventListener("click", () => {
        const answer = document.getElementById(button.getAttribute("aria-controls"));
        if (!answer) return;
        const isExpanded = button.getAttribute("aria-expanded") === "true";
        button.setAttribute("aria-expanded", String(!isExpanded));
        answer.hidden = isExpanded;
    });
});

function readChartData(canvas, field) {
    try {
        const data = JSON.parse(canvas.dataset[field] || "[]");
        return Array.isArray(data) ? data : [];
    } catch (error) {
        console.error("Unable to read report chart data.", error);
        return [];
    }
}

function prepareCanvas(canvas, minimumHeight) {
    const ratio = window.devicePixelRatio || 1;
    const width = Math.max(canvas.clientWidth, 1);
    const height = Math.max(minimumHeight, 1);
    canvas.width = width * ratio;
    canvas.height = height * ratio;
    const context = canvas.getContext("2d");
    context.scale(ratio, ratio);
    return { context, width, height };
}

function drawLineChart(canvas) {
    const labels = readChartData(canvas, "labels");
    const values = readChartData(canvas, "values").map(Number);
    if (!labels.length || labels.length !== values.length) return;

    const { context: ctx, width, height } = prepareCanvas(canvas, 260);
    const padding = { top: 18, right: 16, bottom: 44, left: 38 };
    const chartWidth = width - padding.left - padding.right;
    const chartHeight = height - padding.top - padding.bottom;
    const maximum = Math.max(...values, 1);
    const tickCount = 4;

    ctx.font = "11px Arial";
    ctx.textAlign = "right";
    ctx.textBaseline = "middle";
    for (let tick = 0; tick <= tickCount; tick++) {
        const value = (maximum / tickCount) * tick;
        const y = padding.top + chartHeight - (chartHeight * tick / tickCount);
        ctx.strokeStyle = "#e9e5dc";
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(padding.left, y);
        ctx.lineTo(width - padding.right, y);
        ctx.stroke();
        ctx.fillStyle = "#77736b";
        ctx.fillText(String(Math.round(value)), padding.left - 8, y);
    }

    const pointX = index => labels.length === 1
        ? padding.left + chartWidth / 2
        : padding.left + chartWidth * index / (labels.length - 1);
    const pointY = value => padding.top + chartHeight - (value / maximum) * chartHeight;
    ctx.strokeStyle = "#d99d00";
    ctx.lineWidth = 3;
    ctx.lineJoin = "round";
    ctx.beginPath();
    values.forEach((value, index) => {
        const x = pointX(index);
        const y = pointY(value);
        if (index === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
    });
    ctx.stroke();

    ctx.textAlign = "center";
    ctx.textBaseline = "top";
    const labelStep = Math.max(1, Math.ceil(labels.length / Math.max(1, Math.floor(chartWidth / 62))));
    labels.forEach((label, index) => {
        const x = pointX(index);
        const y = pointY(values[index]);
        ctx.fillStyle = "#d99d00";
        ctx.beginPath();
        ctx.arc(x, y, 4, 0, Math.PI * 2);
        ctx.fill();
        if (index % labelStep === 0 || index === labels.length - 1) {
            ctx.fillStyle = "#77736b";
            ctx.fillText(label, x, height - padding.bottom + 14);
        }
    });
}

function drawDoughnutChart(canvas) {
    const values = readChartData(canvas, "values").map(Number);
    const colors = readChartData(canvas, "colors");
    const total = values.reduce((sum, value) => sum + value, 0);
    if (!values.length || !total) return;

    const { context: ctx, width, height } = prepareCanvas(canvas, 220);
    const radius = Math.min(width, height) * 0.43;
    const thickness = Math.max(16, radius * 0.28);
    let angle = -Math.PI / 2;
    values.forEach((value, index) => {
        const nextAngle = angle + (value / total) * Math.PI * 2;
        ctx.beginPath();
        ctx.arc(width / 2, height / 2, radius, angle, nextAngle);
        ctx.strokeStyle = colors[index] || "#d99d00";
        ctx.lineWidth = thickness;
        ctx.stroke();
        angle = nextAngle;
    });
    ctx.fillStyle = "#292824";
    ctx.font = "800 22px Arial";
    ctx.textAlign = "center";
    ctx.textBaseline = "middle";
    ctx.fillText(String(total), width / 2, height / 2 - 5);
    ctx.fillStyle = "#77736b";
    ctx.font = "11px Arial";
    ctx.fillText("requests", width / 2, height / 2 + 15);
}

const reportCharts = [
    ...Array.from(document.querySelectorAll(".report-line-chart"), canvas => [canvas, drawLineChart]),
    ...Array.from(document.querySelectorAll(".report-doughnut-chart"), canvas => [canvas, drawDoughnutChart])
];

if (reportCharts.length) {
    let resizeTimer;
    const redrawCharts = () => reportCharts.forEach(([canvas, draw]) => draw(canvas));
    redrawCharts();
    window.addEventListener("resize", () => {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(redrawCharts, 120);
    });
}


/* ============================================================
   PASSWORD VISIBILITY & RULE FEEDBACK
   ============================================================ */
document.querySelectorAll(".password-toggle").forEach(btn => {
    btn.addEventListener("click", () => {
        const wrap = btn.closest(".password-wrap");
        const input = wrap ? wrap.querySelector("input") : null;
        if (!input) return;

        const isHidden = input.type === "password";
        input.type = isHidden ? "text" : "password";
        btn.classList.toggle("is-visible", isHidden);
        btn.setAttribute("aria-pressed", String(isHidden));
        btn.setAttribute("aria-label", isHidden ? "Hide password" : "Show password");

        const openIcon = btn.querySelector(".eye-open");
        const closedIcon = btn.querySelector(".eye-closed");
        if (openIcon) openIcon.hidden = isHidden;
        if (closedIcon) closedIcon.hidden = !isHidden;
    });
});

function checkPasswordRules(value) {
    const missing = [];
    if (value.length < 8) missing.push("8+ characters");
    if (!/[A-Z]/.test(value)) missing.push("uppercase letter");
    if (!/[a-z]/.test(value)) missing.push("lowercase letter");
    if (!/[0-9]/.test(value)) missing.push("number");
    if (!/[^A-Za-z0-9]/.test(value)) missing.push("special character");
    return missing;
}

document.querySelectorAll("[data-password-validate]").forEach(input => {
    const wrap = input.closest(".password-wrap");
    const feedback = wrap ? wrap.nextElementSibling : null;

    const updateFeedback = () => {
        const val = input.value;
        const missing = checkPasswordRules(val);

        if (!val) {
            input.classList.remove("input-error", "input-valid");
            input.setCustomValidity("");
            if (feedback && feedback.classList.contains("password-feedback")) {
                feedback.classList.remove("is-error", "is-valid");
                feedback.hidden = false;
                feedback.textContent = "Must be at least 8 characters with uppercase, lowercase, number, and special character.";
            }
            return;
        }

        if (missing.length > 0) {
            input.classList.add("input-error");
            input.classList.remove("input-valid");
            input.setCustomValidity("Password must include: " + missing.join(", "));
            if (feedback && feedback.classList.contains("password-feedback")) {
                feedback.hidden = false;
                feedback.classList.add("is-error");
                feedback.classList.remove("is-valid");
                feedback.textContent = "Typing error — missing: " + missing.join(", ") + ".";
            }
        } else {
            input.classList.remove("input-error");
            input.classList.add("input-valid");
            input.setCustomValidity("");
            if (feedback && feedback.classList.contains("password-feedback")) {
                feedback.hidden = false;
                feedback.classList.remove("is-error");
                feedback.classList.add("is-valid");
                feedback.textContent = "Password follows security rules.";
            }
        }
    };

    input.addEventListener("input", updateFeedback);
});

document.querySelectorAll("[data-confirm-for]").forEach(confirmInput => {
    const targetId = confirmInput.dataset.confirmFor;
    const primaryInput = document.getElementById(targetId);
    const wrap = confirmInput.closest(".password-wrap");
    const feedback = wrap ? wrap.nextElementSibling : null;

    if (!primaryInput) return;

    const checkMatch = () => {
        const val = confirmInput.value;
        if (!val) {
            confirmInput.classList.remove("input-error", "input-valid");
            confirmInput.setCustomValidity("");
            if (feedback) {
                feedback.hidden = true;
                feedback.textContent = "";
                feedback.classList.remove("is-error", "is-valid");
            }
            return;
        }

        if (val !== primaryInput.value) {
            confirmInput.classList.add("input-error");
            confirmInput.classList.remove("input-valid");
            confirmInput.setCustomValidity("Passwords do not match.");
            if (feedback) {
                feedback.hidden = false;
                feedback.classList.add("is-error");
                feedback.classList.remove("is-valid");
                feedback.textContent = "Passwords do not match.";
            }
        } else {
            confirmInput.classList.remove("input-error");
            confirmInput.classList.add("input-valid");
            confirmInput.setCustomValidity("");
            if (feedback) {
                feedback.hidden = false;
                feedback.classList.remove("is-error");
                feedback.classList.add("is-valid");
                feedback.textContent = "Passwords match.";
            }
        }
    };

    confirmInput.addEventListener("input", checkMatch);
    primaryInput.addEventListener("input", () => {
        if (confirmInput.value) checkMatch();
    });
});
