/**
 * Interacciones y prototipado visual para "Mi proyecto" (Estudiante)
 */
document.addEventListener("DOMContentLoaded", () => {
    // 1. Manejo de Pestañas
    const tabButtons = document.querySelectorAll("[data-sw-tab]");
    const tabPanes = document.querySelectorAll(".sw-tab-pane");

    tabButtons.forEach(btn => {
        btn.addEventListener("click", () => {
            const targetTab = btn.dataset.swTab;
            tabButtons.forEach(b => b.classList.remove("is-active"));
            tabPanes.forEach(p => p.classList.remove("is-active"));

            btn.classList.add("is-active");
            const targetPane = document.getElementById(`swTab-${targetTab}`);
            if (targetPane) targetPane.classList.add("is-active");
        });
    });

    // 2. Colapsar / Expandir Paneles (Explorador y Observaciones)
    const toggleExplorerBtn = document.getElementById("swToggleExplorer");
    const explorerPanel = document.getElementById("swExplorerPanel");
    if (toggleExplorerBtn && explorerPanel) {
        toggleExplorerBtn.addEventListener("click", () => {
            explorerPanel.classList.toggle("is-collapsed");
        });
    }

    const toggleObsBtn = document.getElementById("swToggleObs");
    const obsPanel = document.getElementById("swObsPanel");
    if (toggleObsBtn && obsPanel) {
        toggleObsBtn.addEventListener("click", () => {
            obsPanel.classList.toggle("is-collapsed");
        });
    }

    // 3. Recorrido completo desplegable
    const toggleTimelineBtn = document.getElementById("swToggleTimeline");
    const verticalTimeline = document.getElementById("swVerticalTimeline");
    if (toggleTimelineBtn && verticalTimeline) {
        toggleTimelineBtn.addEventListener("click", () => {
            const isHidden = verticalTimeline.hidden;
            verticalTimeline.hidden = !isHidden;
            toggleTimelineBtn.querySelector("span").textContent = isHidden ? "Ocultar recorrido" : "Ver recorrido completo";
        });
    }

    // 4. Selección bidireccional entre marcadores DOCX/PDF y Observaciones
    const obsCards = document.querySelectorAll("[data-obs-id]");
    const docMarkers = document.querySelectorAll("[data-marker-id]");

    function highlightObs(obsId) {
        obsCards.forEach(c => c.classList.toggle("is-selected", c.dataset.obsId === obsId));
        docMarkers.forEach(m => m.classList.toggle("is-active", m.dataset.markerId === obsId));
    }

    obsCards.forEach(card => {
        card.addEventListener("click", () => highlightObs(card.dataset.obsId));
        
        // Checklist local de tareas ☐ Hecha
        const checkbox = card.querySelector(".sw-obs-check");
        if (checkbox) {
            checkbox.addEventListener("change", (e) => {
                e.stopPropagation();
                card.classList.toggle("is-done", checkbox.checked);
            });
        }
    });

    docMarkers.forEach(marker => {
        marker.addEventListener("click", () => highlightObs(marker.dataset.markerId));
    });

    // 5. Modales Simulados
    function openModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.hidden = false;
    }
    function closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.hidden = true;
    }

    document.querySelectorAll("[data-sw-modal-open]").forEach(btn => {
        btn.addEventListener("click", () => openModal(btn.dataset.swModalOpen));
    });
    document.querySelectorAll("[data-sw-modal-close]").forEach(btn => {
        btn.addEventListener("click", () => closeModal(btn.dataset.swModalClose));
    });

    // Toast informativo de simulación
    function showToast(msg) {
        if (typeof window.showToast === "function") {
            window.showToast(msg);
        } else {
            alert(msg);
        }
    }

    // Modal Enviar a revisión
    const confirmSendBtn = document.getElementById("swConfirmSend");
    if (confirmSendBtn) {
        confirmSendBtn.addEventListener("click", () => {
            closeModal("swModalSendReview");
            showToast("Proyecto enviado a revisión correctamente.");
            // Actualización visual simulada
            const statusBadge = document.querySelector(".sw-badge-status");
            if (statusBadge) {
                statusBadge.className = "sw-badge-status is-review";
                statusBadge.innerHTML = '<i class="fa-solid fa-clock"></i> En revisión';
            }
        });
    }

    // Modal Trabajar en Word
    const confirmWordBtn = document.getElementById("swConfirmWordDownload");
    if (confirmWordBtn) {
        confirmWordBtn.addEventListener("click", () => {
            closeModal("swModalWorkWord");
            showToast("Descargando versión actualizada para Word...");
        });
    }

    // 6. Selector Flotante de Simulación en Entorno DEV
    const devSelector = document.getElementById("swDevScenarioSelector");
    if (devSelector) {
        devSelector.addEventListener("change", () => {
            const scenario = devSelector.value;
            showToast(`Simulando escenario visual: ${scenario}`);
            // Recargar o cambiar banderas estéticas locales
            document.querySelectorAll("[data-scenario]").forEach(el => {
                el.hidden = el.dataset.scenario !== scenario && el.dataset.scenario !== "all";
            });
        });
    }
});
