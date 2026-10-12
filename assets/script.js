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

document.querySelectorAll("[data-confirm-delete]").forEach(button => {
    button.addEventListener("click", () => {
        if (!deleteModal) return;
        deleteId.value = button.dataset.confirmDelete || "";
        deleteLabel.textContent = `Request #${button.dataset.confirmDelete} – ${button.dataset.title || "Untitled"}`;
        deleteModal.hidden = false;
    });
});

function hideDeleteModal() { if (deleteModal) deleteModal.hidden = true; }
if (closeDeleteModal) closeDeleteModal.addEventListener("click", hideDeleteModal);
if (cancelDelete) cancelDelete.addEventListener("click", hideDeleteModal);
if (deleteModal) deleteModal.addEventListener("click", e => { if (e.target === deleteModal) hideDeleteModal(); });

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
