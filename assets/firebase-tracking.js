/**
 * CampusFix Firebase Realtime Tracking & Notifications
 * Powered by Google Cloud Firestore on project errorlog-cfdaf
 */

(function () {
    const firebaseConfig = {
        apiKey: "AIzaSyAHmsoA4d2mSsxdT4Ax0AYeW6TyUalx3L4",
        authDomain: "errorlog-cfdaf.firebaseapp.com",
        projectId: "errorlog-cfdaf",
        storageBucket: "errorlog-cfdaf.firebasestorage.app",
        messagingSenderId: "481170131280",
        appId: "1:481170131280:web:89f1650531a88be4c80455"
    };

    if (typeof firebase === "undefined") {
        console.warn("CampusFix: Firebase SDK not loaded.");
        return;
    }

    if (!firebase.apps.length) {
        firebase.initializeApp(firebaseConfig);
    }

    const db = firebase.firestore();

    // Toast Container
    let toastContainer = document.querySelector(".cf-toast-container");
    if (!toastContainer) {
        toastContainer = document.createElement("div");
        toastContainer.className = "cf-toast-container";
        document.body.appendChild(toastContainer);
    }

    function showToast(title, message, icon = "🔔") {
        const toast = document.createElement("div");
        toast.className = "cf-toast";
        toast.innerHTML = `
            <div class="cf-toast-icon">${icon}</div>
            <div class="cf-toast-body">
                <strong>${escapeHtml(title)}</strong>
                <span>${escapeHtml(message)}</span>
            </div>
        `;
        toastContainer.appendChild(toast);

        setTimeout(() => {
            toast.classList.add("fade-out");
            setTimeout(() => toast.remove(), 320);
        }, 5000);
    }

    function escapeHtml(text) {
        const div = document.createElement("div");
        div.textContent = text || "";
        return div.innerHTML;
    }

    function formatTime(isoOrSqlString) {
        if (!isoOrSqlString) return "Not started";
        const date = new Date(isoOrSqlString.replace(/-/g, "/"));
        if (isNaN(date.getTime())) return isoOrSqlString;

        const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        const m = months[date.getMonth()];
        const d = date.getDate();
        const y = date.getFullYear();

        let hours = date.getHours();
        const minutes = date.getMinutes().toString().padStart(2, "0");
        const ampm = hours >= 12 ? "PM" : "AM";
        hours = hours % 12 || 12;

        return `${m} ${d}, ${y} · ${hours}:${minutes} ${ampm}`;
    }

    function sendBrowserNotification(title, body) {
        if ("Notification" in window && Notification.permission === "granted") {
            try {
                new Notification(title, {
                    body: body,
                    icon: "assets/cflogo.png"
                });
            } catch (err) {
                console.warn("Notification error:", err);
            }
        }
    }

    // 1. Single Request Tracking on tracking.php
    const trackingContainer = document.getElementById("trackingContainer");
    if (trackingContainer) {
        const requestId = trackingContainer.dataset.requestId;
        let lastStatus = trackingContainer.dataset.initialStatus;
        let isInitialLoad = true;

        const liveNote = document.getElementById("liveStatusNote");
        if (liveNote) {
            liveNote.innerHTML = `<span class="live-pulse"></span>Live tracking active`;
            liveNote.className = "tracking-refresh-note live-badge";

            // If browser supports notifications, add request permission button if not determined
            if ("Notification" in window && Notification.permission === "default") {
                const btnNotify = document.createElement("button");
                btnNotify.className = "btn-notify-perm";
                btnNotify.textContent = "🔔 Enable Push Alerts";
                btnNotify.addEventListener("click", () => {
                    Notification.requestPermission().then(permission => {
                        if (permission === "granted") {
                            btnNotify.remove();
                            showToast("Push Alerts Enabled", "You will receive desktop alerts when request progress updates.", "✅");
                        } else {
                            btnNotify.remove();
                        }
                    });
                });
                liveNote.appendChild(btnNotify);
            }
        }

        db.collection("requests").doc(String(requestId)).onSnapshot(doc => {
            if (!doc.exists) return;
            const data = doc.data();

            if (isInitialLoad) {
                isInitialLoad = false;
                lastStatus = data.status;
                return;
            }

            // Has status or progress changed?
            const statusChanged = data.status !== lastStatus;
            lastStatus = data.status;

            // Update Status Badge
            const statusBadge = document.getElementById("requestStatusBadge");
            if (statusBadge && data.status) {
                statusBadge.textContent = data.status;
                const slug = data.status.toLowerCase().replace(/\s+/g, "-");
                statusBadge.className = `status status-${slug}`;
            }

            // Update Assigned Personnel
            const assignedEl = document.getElementById("assignedPersonnelName");
            if (assignedEl && data.assigned_name) {
                assignedEl.textContent = data.assigned_name;
            }

            // Update Last Updated time
            const updatedEl = document.getElementById("lastUpdatedTime");
            if (updatedEl && data.updated_at) {
                updatedEl.textContent = formatTime(data.updated_at);
            }

            // Update Timeline stages
            if (data.stages) {
                const stagesDef = [
                    { key: "submitted", label: "Request Submitted" },
                    { key: "reviewed",  label: "Request Reviewed" },
                    { key: "assigned",  label: "Assigned to Maintenance" },
                    { key: "repair",    label: "Repair" },
                    { key: "resolved",  label: "Resolved" }
                ];

                const currentStage = data.status === "Assigned" ? "assigned" : (data.status === "In Progress" ? "repair" : null);

                stagesDef.forEach((def, index) => {
                    const li = document.querySelector(`.tracking-stage[data-stage="${def.key}"]`);
                    if (!li) return;

                    const stageTime = data.stages[def.key] || null;
                    const isResolved = Boolean(data.stages.resolved);
                    const isCurrent = def.key === currentStage && !isResolved;
                    const isDone = def.key === "submitted"
                        || (Boolean(stageTime) && !isCurrent)
                        || data.status === "Completed";

                    const state = isDone ? "done" : (isCurrent ? "current" : "upcoming");
                    li.className = `tracking-stage ${state}`;

                    const marker = li.querySelector(".tracking-marker");
                    if (marker) {
                        marker.textContent = isDone ? "✓" : (isCurrent ? "●" : String(index + 1));
                    }

                    const timeEl = li.querySelector("time");
                    if (timeEl) {
                        timeEl.textContent = formatTime(stageTime);
                    }

                    let small = li.querySelector("small");
                    if (isCurrent) {
                        if (!small) {
                            small = document.createElement("small");
                            small.textContent = "Currently in progress";
                            li.querySelector(".tracking-stage-content").appendChild(small);
                        }
                    } else if (small) {
                        small.remove();
                    }
                });
            }

            if (statusChanged) {
                showToast(`Request #${requestId} Updated`, `Status changed to ${data.status}`, "⚡");
                sendBrowserNotification(`CampusFix: Request #${requestId}`, `Status updated to ${data.status}`);
            }
        }, err => {
            console.warn("Firestore tracking snapshot error:", err);
        });
    }

    // 2. Global live notifications on Dashboard & My Requests
    const liveWatcher = document.getElementById("firebaseLiveWatcher");
    if (liveWatcher) {
        const requesterId = parseInt(liveWatcher.dataset.requesterId, 10);
        if (requesterId) {
            let initialSnapshot = true;
            db.collection("requests").where("requester_id", "==", requesterId).onSnapshot(snapshot => {
                if (initialSnapshot) {
                    initialSnapshot = false;
                    return;
                }

                snapshot.docChanges().forEach(change => {
                    if (change.type === "modified") {
                        const data = change.doc.data();
                        showToast(`Request #${data.id || change.doc.id} Updated`, `${data.title}: Status changed to ${data.status}`, "🔔");
                        sendBrowserNotification(`CampusFix Request Updated`, `#${data.id || change.doc.id} is now ${data.status}`);
                    }
                });
            }, err => {
                console.warn("Firestore watcher error:", err);
            });
        }
    }
})();
