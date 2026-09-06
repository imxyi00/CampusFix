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
