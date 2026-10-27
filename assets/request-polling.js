(function () {
    const watcher = document.getElementById("requestUpdatesPoller")
        || document.getElementById("firebaseLiveWatcher")
        || document.getElementById("trackingContainer");
    if (!watcher) return;
    if (watcher.dataset.firebaseSyncAvailable === "true"
        && typeof firebase !== "undefined"
        && firebase.apps.length > 0) return;

    let cursor = Number.parseInt(watcher.dataset.serverTime || "", 10);
    if (!Number.isSafeInteger(cursor) || cursor < 0) {
        console.error("CampusFix request polling is missing a valid server time.");
        return;
    }

    const userId = Number.parseInt(watcher.dataset.userId || watcher.dataset.requesterId || "", 10);
    const userRole = watcher.dataset.userRole || "requester";
    const knownRequests = new Map();
    const endpoint = new URL("request_updates.php", document.baseURI);
    let initialPoll = true;
    let polling = false;
    let pollingStopped = false;

    function updateVisibleStatus(request) {
        document.querySelectorAll("[data-live-request-id]").forEach(element => {
            if (Number(element.dataset.liveRequestId) !== request.id) return;
            const badge = element.querySelector("[data-live-status]");
            if (badge) {
                badge.textContent = request.status;
                badge.className = `status status-${request.status.toLowerCase().replace(/\s+/g, "-")}`;
            }
            const assignee = element.querySelector("[data-live-assignee]");
            if (assignee) assignee.textContent = request.assigned_name || "Not assigned";
        });

        const tracking = document.getElementById("trackingContainer");
        if (!tracking || Number(tracking.dataset.requestId) !== request.id) return;

        const badge = document.getElementById("requestStatusBadge");
        if (badge) {
            badge.textContent = request.status;
            badge.className = `status status-${request.status.toLowerCase().replace(/\s+/g, "-")}`;
        }
        const assignedName = document.getElementById("assignedPersonnelName");
        if (assignedName) assignedName.textContent = request.assigned_name || "Not assigned";
        const updatedAt = document.getElementById("lastUpdatedTime");
        if (updatedAt) updatedAt.textContent = request.updated_at;
        if (request.completion_photo) {
            const proofBlock = document.getElementById("completionEvidenceBlock");
            const proofImage = document.getElementById("completionEvidencePhoto");
            if (proofBlock && proofImage) {
                proofImage.src = `uploads/${encodeURIComponent(request.completion_photo)}`;
                proofBlock.hidden = false;
            }
        }
        if (request.stages) updateTrackingTimeline(request);
    }

    function formatTime(value) {
        if (!value) return "Not started";
        const date = new Date(value.replace(/-/g, "/"));
        return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
    }

    function updateTrackingTimeline(request) {
        const stages = [
            { key: "submitted", label: "Request Submitted" },
            { key: "reviewed", label: "Request Reviewed" },
            { key: "assigned", label: "Assigned to Personnel" },
            { key: "repair", label: "Repair" },
            { key: "resolved", label: "Resolved" }
        ];
        const currentStage = request.status === "Assigned"
            ? "assigned"
            : (request.status === "In Progress" ? "repair" : null);

        stages.forEach((stage, index) => {
            const item = document.querySelector(`.tracking-stage[data-stage="${stage.key}"]`);
            if (!item) return;
            const completedAt = request.stages[stage.key];
            const isResolved = Boolean(request.stages.resolved);
            const isCurrent = stage.key === currentStage && !isResolved;
            const isDone = stage.key === "submitted"
                || (Boolean(completedAt) && !isCurrent)
                || request.status === "Completed";
            item.className = `tracking-stage ${isDone ? "done" : (isCurrent ? "current" : "upcoming")}`;

            const marker = item.querySelector(".tracking-marker");
            if (marker) marker.textContent = isDone ? "✓" : (isCurrent ? "●" : String(index + 1));
            const time = item.querySelector("time");
            if (time) time.textContent = formatTime(completedAt);
            let note = item.querySelector("small");
            if (isCurrent && !note) {
                note = document.createElement("small");
                note.textContent = "Currently in progress";
                item.querySelector(".tracking-stage-content").appendChild(note);
            } else if (!isCurrent && note) {
                note.remove();
            }
        });
    }

    function requestFingerprint(request) {
        return {
            value: [request.status, request.assigned_to, request.updated_at].join("|"),
            status: request.status,
            assignedTo: request.assigned_to
        };
    }

    async function poll() {
        if (polling || pollingStopped) return;
        polling = true;
        endpoint.searchParams.set("since", String(cursor));

        try {
            const response = await fetch(endpoint, {
                credentials: "same-origin",
                cache: "no-store",
                headers: { Accept: "application/json" }
            });
            if (response.status === 401) {
                pollingStopped = true;
                window.location.assign("index.php?error=" + encodeURIComponent("Your session expired. Please sign in again."));
                return;
            }
            if (!response.ok) {
                throw new Error(`Request updates returned HTTP ${response.status}.`);
            }

            const payload = await response.json();
            if (!Number.isSafeInteger(payload.server_time) || !Array.isArray(payload.requests)) {
                throw new Error("Request updates returned an invalid response.");
            }

            for (const request of payload.requests) {
                const fingerprint = requestFingerprint(request);
                const previous = knownRequests.get(request.id);
                updateVisibleStatus(request);

                if (!initialPoll && previous && previous.value !== fingerprint.value) {
                    const message = previous.status !== fingerprint.status
                        ? `${request.title}: status is now ${request.status}`
                        : `Assignment updated: ${request.assigned_name || "Not assigned"}`;
                    window.campusfixShowToast(
                        `Request #${request.id} Updated`,
                        message,
                        "🔔"
                    );
                } else if (!initialPoll && !previous && request.created_at) {
                    const createdAt = Date.parse(request.created_at.replace(" ", "T"));
                    if (Number.isFinite(createdAt) && createdAt >= (cursor - 1) * 1000) {
                        if (userRole === "admin_staff" || userRole === "system_admin") {
                            window.campusfixShowToast(`New Request #${request.id}`, `${request.title} (${request.category})`, "📥");
                        } else if ((userRole === "maintenance" || userRole === "itso")
                            && Number(request.assigned_to) === userId) {
                            window.campusfixShowToast(`New Task Assigned (#${request.id})`, `${request.title} · ${request.location}`, "🛠️");
                        }
                    }
                }

                knownRequests.set(request.id, fingerprint);
            }

            cursor = Math.max(cursor, payload.server_time);
            initialPoll = false;

            const liveNote = document.getElementById("liveStatusNote");
            if (liveNote) {
                liveNote.textContent = "Live updates active";
                liveNote.className = "tracking-refresh-note live-badge";
            }
        } catch (error) {
            console.error("CampusFix could not refresh request updates.", error);
            const liveNote = document.getElementById("liveStatusNote");
            if (liveNote) {
                liveNote.textContent = "Live updates reconnecting";
                liveNote.className = "tracking-refresh-note";
            }
        } finally {
            polling = false;
        }
    }

    poll();
    window.setInterval(poll, 8000);
})();
