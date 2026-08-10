(() => {
    // -------------------------------------------------------------
    // 1. Formulario de Datos Personales (Fase 2)
    // -------------------------------------------------------------
    const form = document.querySelector('[data-profile-form]');
    const confirmModal = document.getElementById('profileConfirm');
    const currentPasswordInput = document.getElementById('profileCurrentPassword');
    const submitButton = document.querySelector('[data-profile-submit]');
    const confirmSubmitBtn = document.querySelector('[data-profile-confirm]');
    const cancelSubmitBtn = document.querySelector('[data-profile-cancel]');

    if (confirmModal && confirmModal.parentElement !== document.body) {
        document.body.appendChild(confirmModal);
    }

    if (form && confirmModal && currentPasswordInput && submitButton && confirmSubmitBtn && cancelSubmitBtn) {
        const initial = {
            fullName: String(form.elements.full_name?.value ?? '').trim(),
            email: String(form.elements.email?.value ?? '').trim().toLowerCase(),
            username: String(form.elements.username?.value ?? '').trim(),
        };
        let confirmationAccepted = false;

        const hasChanges = () => (
            String(form.elements.full_name?.value ?? '').trim() !== initial.fullName
            || String(form.elements.email?.value ?? '').trim().toLowerCase() !== initial.email
            || String(form.elements.username?.value ?? '').trim() !== initial.username
        );

        const syncSubmitState = () => {
            const changed = hasChanges();
            submitButton.disabled = !changed;
            submitButton.setAttribute('aria-disabled', String(!changed));
        };

        const closeConfirmModal = () => {
            confirmModal.hidden = true;
            currentPasswordInput.value = '';
            confirmationAccepted = false;
            document.body.classList.remove('profile-modal-open');
            document.body.style.overflow = '';
        };

        const openConfirmModal = () => {
            currentPasswordInput.value = '';
            confirmModal.hidden = false;
            document.body.classList.add('profile-modal-open');
            document.body.style.overflow = 'hidden';
            window.setTimeout(() => currentPasswordInput.focus(), 0);
        };

        form.addEventListener('input', syncSubmitState);
        form.addEventListener('submit', (event) => {
            if (confirmationAccepted) return;
            event.preventDefault();
            if (!hasChanges()) return;
            if (!form.reportValidity()) return;
            openConfirmModal();
        });

        confirmSubmitBtn.addEventListener('click', () => {
            if (!currentPasswordInput.value) {
                currentPasswordInput.reportValidity();
                currentPasswordInput.focus();
                return;
            }
            confirmationAccepted = true;
            confirmModal.hidden = true;
            document.body.classList.remove('profile-modal-open');
            document.body.style.overflow = '';
            form.requestSubmit();
        });

        cancelSubmitBtn.addEventListener('click', closeConfirmModal);
        confirmModal.addEventListener('click', (event) => {
            if (event.target === confirmModal) closeConfirmModal();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !confirmModal.hidden) closeConfirmModal();
        });

        syncSubmitState();
    }

    // -------------------------------------------------------------
    // 2. Editor de Fotografía de Perfil (Fase 3B)
    // -------------------------------------------------------------
    const shell = document.querySelector('.profile-shell');
    if (!shell) return;

    const updateEndpoint = shell.dataset.updateEndpoint;
    const removeEndpoint = shell.dataset.removeEndpoint;
    const avatarCsrf = shell.dataset.avatarCsrf;

    const avatarFileInput = document.getElementById('profileAvatarFileInput');
    const avatarAddBtn = document.getElementById('profileAvatarAddBtn');
    const avatarRemoveBtn = document.getElementById('profileAvatarRemoveBtn');

    const editModal = document.getElementById('profileAvatarEditModal');
    const editCloseBtn = document.getElementById('profileAvatarEditCloseBtn');
    const editCancelBtn = document.getElementById('profileAvatarEditCancelBtn');
    const editSaveBtn = document.getElementById('profileAvatarEditSaveBtn');
    const cropViewport = document.getElementById('profileCropViewport');
    const cropImage = document.getElementById('profileCropImage');
    const zoomInput = document.getElementById('profileCropZoom');
    const previewCanvas = document.getElementById('profileCropPreviewCanvas');

    const removeModal = document.getElementById('profileAvatarRemoveModal');
    const removeCancelBtn = document.getElementById('profileAvatarRemoveCancelBtn');
    const removeConfirmBtn = document.getElementById('profileAvatarRemoveConfirmBtn');

    const avatarAlert = document.getElementById('profileAvatarAlert');
    const avatarDisplay = document.getElementById('profileAvatarDisplay');

    // Mover modales a document.body para eliminar contextos de apilamiento limitados por el layout
    if (editModal && editModal.parentElement !== document.body) document.body.appendChild(editModal);
    if (removeModal && removeModal.parentElement !== document.body) document.body.appendChild(removeModal);

    let previousActiveElement = null;
    let loadedRawImage = null;
    let minScale = 1;
    let maxScale = 3;
    let currentScale = 1;
    let posX = 0;
    let posY = 0;
    let isDragging = false;
    let startX = 0;
    let startY = 0;

    const showAlert = (message, type = 'info') => {
        if (!avatarAlert) return;
        avatarAlert.className = `profile-message ${type}`;
        avatarAlert.textContent = message;
        avatarAlert.hidden = false;
        avatarAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    const hideAlert = () => {
        if (avatarAlert) avatarAlert.hidden = true;
    };

    const getViewportSize = () => {
        if (!cropViewport) return 280;
        const rect = cropViewport.getBoundingClientRect();
        return (rect.width > 0) ? rect.width : 280;
    };

    const clampBounds = () => {
        if (!loadedRawImage) return;
        const vSize = getViewportSize();
        const nw = loadedRawImage.naturalWidth || 800;
        const nh = loadedRawImage.naturalHeight || 800;
        const scaledW = nw * currentScale;
        const scaledH = nh * currentScale;
        const minX = vSize - scaledW;
        const minY = vSize - scaledH;
        posX = Math.min(0, Math.max(minX, posX));
        posY = Math.min(0, Math.max(minY, posY));
    };

    const renderPreview = () => {
        if (!loadedRawImage || !previewCanvas) return;
        const ctx = previewCanvas.getContext('2d');
        if (!ctx) return;
        const vSize = getViewportSize();

        ctx.clearRect(0, 0, 70, 70);
        ctx.save();
        ctx.beginPath();
        ctx.arc(35, 35, 35, 0, Math.PI * 2);
        ctx.clip();

        const srcX = (-posX) / currentScale;
        const srcY = (-posY) / currentScale;
        const srcW = vSize / currentScale;
        const srcH = vSize / currentScale;

        ctx.drawImage(loadedRawImage, srcX, srcY, srcW, srcH, 0, 0, 70, 70);
        ctx.restore();
    };

    const applyTransform = () => {
        if (!cropImage) return;
        cropImage.style.transform = `translate(${posX}px, ${posY}px) scale(${currentScale})`;
        renderPreview();
    };

    const openEditModal = () => {
        if (!editModal) return;
        previousActiveElement = document.activeElement;
        editModal.hidden = false;
        document.body.classList.add('profile-modal-open');
        document.body.style.overflow = 'hidden';
        window.setTimeout(() => editSaveBtn?.focus(), 50);
    };

    const closeEditModal = () => {
        if (!editModal) return;
        editModal.hidden = true;
        document.body.classList.remove('profile-modal-open');
        document.body.style.overflow = '';
        if (previousActiveElement && typeof previousActiveElement.focus === 'function') {
            previousActiveElement.focus();
        }
    };

    const openRemoveModal = () => {
        if (!removeModal) return;
        previousActiveElement = document.activeElement;
        removeModal.hidden = false;
        document.body.classList.add('profile-modal-open');
        document.body.style.overflow = 'hidden';
        window.setTimeout(() => removeConfirmBtn?.focus(), 50);
    };

    const closeRemoveModal = () => {
        if (!removeModal) return;
        removeModal.hidden = true;
        document.body.classList.remove('profile-modal-open');
        document.body.style.overflow = '';
        if (previousActiveElement && typeof previousActiveElement.focus === 'function') {
            previousActiveElement.focus();
        }
    };

    const initCropEngine = () => {
        if (!loadedRawImage || !cropViewport || !zoomInput || !cropImage) return;
        const vSize = getViewportSize();
        const nw = loadedRawImage.naturalWidth || 800;
        const nh = loadedRawImage.naturalHeight || 800;

        minScale = Math.max(vSize / nw, vSize / nh);
        maxScale = minScale * 3.5;
        currentScale = minScale;

        zoomInput.min = String(minScale);
        zoomInput.max = String(maxScale);
        zoomInput.step = String((maxScale - minScale) / 200);
        zoomInput.value = String(minScale);

        posX = (vSize - nw * currentScale) / 2;
        posY = (vSize - nh * currentScale) / 2;

        cropImage.style.width = `${nw}px`;
        cropImage.style.height = `${nh}px`;

        clampBounds();
        applyTransform();
    };

    // --- Selección de Archivo y Carga Previa al Despliegue del Modal ---
    const handleFileSelect = (file) => {
        if (!file) return;
        hideAlert();

        if (file.size < 1) {
            showAlert('La imagen seleccionada está vacía o no es válida.', 'error');
            return;
        }

        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        if (!allowedTypes.includes(file.type.toLowerCase())) {
            showAlert('Solo se permiten imágenes JPG, JPEG o PNG.', 'error');
            return;
        }

        const maxSizeBytes = 5 * 1024 * 1024;
        if (file.size > maxSizeBytes) {
            showAlert('La imagen supera el límite máximo de 5 MB.', 'error');
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                loadedRawImage = img;
                if (cropImage) cropImage.src = e.target.result;
                openEditModal();
                initCropEngine();
            };
            img.onerror = () => {
                showAlert('La imagen seleccionada está vacía o no es válida.', 'error');
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    };

    avatarAddBtn?.addEventListener('click', () => avatarFileInput?.click());

    avatarFileInput?.addEventListener('change', (e) => {
        const file = e.target.files?.[0];
        if (file) handleFileSelect(file);
        e.target.value = '';
    });

    // --- Movimiento mediante Pointer Events ---
    cropViewport?.addEventListener('pointerdown', (e) => {
        if (!loadedRawImage) return;
        isDragging = true;
        startX = e.clientX - posX;
        startY = e.clientY - posY;
        cropViewport.setPointerCapture(e.pointerId);
        cropViewport.classList.add('is-dragging');
    });

    cropViewport?.addEventListener('pointermove', (e) => {
        if (!isDragging) return;
        posX = e.clientX - startX;
        posY = e.clientY - startY;
        clampBounds();
        applyTransform();
    });

    const stopDragging = (e) => {
        if (!isDragging) return;
        isDragging = false;
        cropViewport?.classList.remove('is-dragging');
        if (e && e.pointerId) {
            try { cropViewport?.releasePointerCapture(e.pointerId); } catch (_) {}
        }
    };

    cropViewport?.addEventListener('pointerup', stopDragging);
    cropViewport?.addEventListener('pointercancel', stopDragging);

    // --- Control de Zoom ---
    zoomInput?.addEventListener('input', () => {
        if (!loadedRawImage) return;
        const newScale = parseFloat(zoomInput.value);
        const vSize = getViewportSize();

        const centerImgX = (vSize / 2 - posX) / currentScale;
        const centerImgY = (vSize / 2 - posY) / currentScale;

        currentScale = newScale;
        posX = vSize / 2 - centerImgX * currentScale;
        posY = vSize / 2 - centerImgY * currentScale;

        clampBounds();
        applyTransform();
    });

    // --- Generar Recorte 1:1 (Canvas 800×800) y Guardar ---
    editSaveBtn?.addEventListener('click', async () => {
        if (!loadedRawImage || !updateEndpoint) return;

        editSaveBtn.disabled = true;
        editCancelBtn.disabled = true;
        editSaveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Guardando...';

        const canvas = document.createElement('canvas');
        canvas.width = 800;
        canvas.height = 800;
        const ctx = canvas.getContext('2d');

        const vSize = getViewportSize();
        const srcX = (-posX) / currentScale;
        const srcY = (-posY) / currentScale;
        const srcW = vSize / currentScale;
        const srcH = vSize / currentScale;

        ctx.drawImage(loadedRawImage, srcX, srcY, srcW, srcH, 0, 0, 800, 800);

        canvas.toBlob(async (blob) => {
            if (!blob) {
                showAlert('No fue posible procesar la imagen.', 'error');
                editSaveBtn.disabled = false;
                editCancelBtn.disabled = false;
                editSaveBtn.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i> Usar fotografía';
                return;
            }

            const data = new FormData();
            data.append('avatar', blob, 'avatar.jpg');
            data.append('_csrf', avatarCsrf || '');

            try {
                const response = await fetch(updateEndpoint, { method: 'POST', body: data });
                const result = await response.json().catch(() => ({ success: false, message: 'No se pudo actualizar la fotografía. Inténtalo nuevamente.' }));

                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'No se pudo actualizar la fotografía. Inténtalo nuevamente.');
                }

                updateAvatarDOM(result.data?.avatar_url);
                closeEditModal();
                showAlert(result.message || 'Fotografía de perfil actualizada correctamente.', 'success');
            } catch (error) {
                showAlert(error.message || 'No se pudo actualizar la fotografía. Inténtalo nuevamente.', 'error');
            } finally {
                editSaveBtn.disabled = false;
                editCancelBtn.disabled = false;
                editSaveBtn.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i> Usar fotografía';
            }
        }, 'image/jpeg', 0.9);
    });

    // --- Cierre de Modal de Edición ---
    editCloseBtn?.addEventListener('click', closeEditModal);
    editCancelBtn?.addEventListener('click', closeEditModal);
    editModal?.addEventListener('click', (e) => {
        if (e.target === editModal) closeEditModal();
    });

    // --- Modal de Eliminación de Fotografía ---
    avatarRemoveBtn?.addEventListener('click', openRemoveModal);
    removeCancelBtn?.addEventListener('click', closeRemoveModal);
    removeModal?.addEventListener('click', (e) => {
        if (e.target === removeModal) closeRemoveModal();
    });

    removeConfirmBtn?.addEventListener('click', async () => {
        if (!removeEndpoint) return;

        removeConfirmBtn.disabled = true;
        removeCancelBtn.disabled = true;
        removeConfirmBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Eliminando...';

        const data = new FormData();
        data.append('_csrf', avatarCsrf || '');

        try {
            const response = await fetch(removeEndpoint, { method: 'POST', body: data });
            const result = await response.json().catch(() => ({ success: false, message: 'No se pudo eliminar la fotografía. Inténtalo nuevamente.' }));

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'No se pudo eliminar la fotografía. Inténtalo nuevamente.');
            }

            updateAvatarDOM(null);
            closeRemoveModal();
            showAlert(result.message || 'Fotografía de perfil eliminada correctamente.', 'success');
        } catch (error) {
            showAlert(error.message || 'No se pudo eliminar la fotografía. Inténtalo nuevamente.', 'error');
        } finally {
            removeConfirmBtn.disabled = false;
            removeCancelBtn.disabled = false;
            removeConfirmBtn.innerHTML = 'Eliminar fotografía';
        }
    });

    // --- Manejo Tecla Escape ---
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (editModal && !editModal.hidden) closeEditModal();
            if (removeModal && !removeModal.hidden) closeRemoveModal();
        }
    });

    // --- Actualización Dinámica del DOM y Cache-Busting ---
    const updateAvatarDOM = (rawAvatarUrl) => {
        const timestamp = Date.now();
        const avatarUrl = rawAvatarUrl ? `${rawAvatarUrl}${rawAvatarUrl.includes('?') ? '&' : '?'}t=${timestamp}` : null;

        // 1. Actualizar perfil principal
        if (avatarDisplay) {
            if (avatarUrl) {
                let img = avatarDisplay.querySelector('img');
                if (!img) {
                    img = document.createElement('img');
                    img.id = 'profileHeaderAvatarImg';
                    avatarDisplay.innerHTML = '';
                    avatarDisplay.appendChild(img);
                }
                img.src = avatarUrl;
                img.alt = 'Fotografía de perfil';
            } else {
                const initialLetter = (document.querySelector('.profile-header-info h1')?.textContent ?? 'U').trim().charAt(0).toUpperCase();
                avatarDisplay.innerHTML = `<span id="profileHeaderAvatarInitial" class="profile-initial">${initialLetter}</span>`;
            }
        }

        // 2. Conmutar botón de eliminación
        if (avatarRemoveBtn) avatarRemoveBtn.hidden = !Boolean(avatarUrl);

        // 3. Actualizar avatar del header / menú superior (si existen en el layout)
        const userAvatarBtn = document.getElementById('avatarButton');
        if (userAvatarBtn) {
            if (avatarUrl) {
                userAvatarBtn.innerHTML = `<img src="${avatarUrl}" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`;
            } else {
                const initialLetter = (document.querySelector('.profile-header-info h1')?.textContent ?? 'U').trim().charAt(0).toUpperCase();
                userAvatarBtn.textContent = initialLetter;
            }
        }
    };
})();
