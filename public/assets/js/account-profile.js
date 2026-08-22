(() => {
    let pendingAvatarFile = null;
    let pendingAvatarPreviewUrl = null;
    let avatarAction = 'none';
    let syncSubmitState = () => {};
    let isProfileDirty = () => false;
    let isLeavingProfile = false;
    let profileSubmitInProgress = false;

    // -------------------------------------------------------------
    // 1. Formulario de Datos Personales (Fase 2)
    // -------------------------------------------------------------
    const form = document.querySelector('[data-profile-form]');
    const confirmModal = document.getElementById('profileConfirm');
    const unsavedModal = document.getElementById('profileUnsavedModal');
    const submitButton = document.querySelector('[data-profile-submit]');
    const confirmSubmitBtn = document.querySelector('[data-profile-confirm]');
    const cancelSubmitBtn = document.querySelector('[data-profile-cancel]');
    const unsavedCancelBtn = document.querySelector('[data-profile-unsaved-cancel]');
    const unsavedConfirmBtn = document.querySelector('[data-profile-unsaved-confirm]');

    if (confirmModal && confirmModal.parentElement !== document.body) {
        document.body.appendChild(confirmModal);
    }
    if (unsavedModal && unsavedModal.parentElement !== document.body) {
        document.body.appendChild(unsavedModal);
    }

    if (form && confirmModal && submitButton && confirmSubmitBtn && cancelSubmitBtn) {
        let confirmationAccepted = false;

        const originalProfile = Object.freeze({
            fullName: String(form.elements.full_name?.value ?? '').trim(),
            email: String(form.elements.email?.value ?? '').trim().toLowerCase(),
            username: String(form.elements.username?.value ?? '').trim(),
        });

        isProfileDirty = () => (
            String(form.elements.full_name?.value ?? '').trim() !== originalProfile.fullName
            || String(form.elements.email?.value ?? '').trim().toLowerCase() !== originalProfile.email
            || String(form.elements.username?.value ?? '').trim() !== originalProfile.username
            || pendingAvatarFile !== null
            || avatarAction !== 'none'
        );

        syncSubmitState = () => {
            const changed = isProfileDirty();
            submitButton.disabled = !changed;
            submitButton.setAttribute('aria-disabled', String(!changed));
        };

        const closeConfirmModal = () => {
            confirmModal.hidden = true;
            confirmationAccepted = false;
            profileSubmitInProgress = false;
            confirmSubmitBtn.disabled = false;
            cancelSubmitBtn.disabled = false;
            confirmSubmitBtn.textContent = 'Guardar cambios';
            document.body.classList.remove('profile-modal-open');
            document.body.style.overflow = '';
        };

        const openConfirmModal = () => {
            confirmModal.hidden = false;
            document.body.classList.add('profile-modal-open');
            document.body.style.overflow = 'hidden';
        };

        form.addEventListener('input', syncSubmitState);
        form.addEventListener('submit', (event) => {
            if (confirmationAccepted) return;
            event.preventDefault();
            if (!isProfileDirty()) return;
            if (!form.reportValidity()) return;
            openConfirmModal();
        });

        confirmSubmitBtn.addEventListener('click', () => {
            if (profileSubmitInProgress) return;
            profileSubmitInProgress = true;
            confirmationAccepted = true;
            isLeavingProfile = true;
            confirmSubmitBtn.disabled = true;
            cancelSubmitBtn.disabled = true;
            confirmSubmitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Guardando...';
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

    const avatarFileInput = document.getElementById('profileAvatarFileInput');
    const avatarAddBtn = document.getElementById('profileAvatarAddBtn');
    const avatarRemoveBtn = document.getElementById('profileAvatarRemoveBtn');
    const avatarRemovePendingInput = document.getElementById('profileAvatarRemovePending');

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
    const originalAvatarUrl = avatarDisplay?.querySelector('img')?.getAttribute('src') || null;
    const originalHasAvatar = originalAvatarUrl !== null;

    let pendingNavigation = null;
    let pendingNavigationTrigger = null;
    const closeUnsavedModal = () => {
        if (!unsavedModal) return;
        unsavedModal.hidden = true;
        document.body.classList.remove('profile-modal-open');
        document.body.style.overflow = '';
        pendingNavigation = null;
        if (pendingNavigationTrigger && typeof pendingNavigationTrigger.focus === 'function') {
            pendingNavigationTrigger.focus();
        }
        pendingNavigationTrigger = null;
    };

    const openUnsavedModal = (action, trigger = null) => {
        if (!unsavedModal) return action();
        pendingNavigation = action;
        pendingNavigationTrigger = trigger;
        unsavedModal.hidden = false;
        document.body.classList.add('profile-modal-open');
        document.body.style.overflow = 'hidden';
        window.setTimeout(() => unsavedCancelBtn?.focus(), 0);
    };

    unsavedCancelBtn?.addEventListener('click', closeUnsavedModal);
    unsavedModal?.addEventListener('click', event => {
        if (event.target === unsavedModal) closeUnsavedModal();
    });
    unsavedConfirmBtn?.addEventListener('click', () => {
        const action = pendingNavigation;
        isLeavingProfile = true;
        closeUnsavedModal();
        action?.();
    });

    // Mover modales a document.body para eliminar contextos de apilamiento limitados por el layout
    if (editModal && editModal.parentElement !== document.body) document.body.appendChild(editModal);
    if (removeModal && removeModal.parentElement !== document.body) document.body.appendChild(removeModal);

    window.addEventListener('beforeunload', event => {
        if (!isLeavingProfile && isProfileDirty()) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    document.addEventListener('click', event => {
        const link = event.target.closest?.('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        if (link.target === '_blank' || link.hasAttribute('download')) return;

        let destination;
        try { destination = new URL(link.href, window.location.href); } catch (_) { return; }
        if (destination.origin !== window.location.origin || !isProfileDirty()) return;

        event.preventDefault();
        openUnsavedModal(() => { window.location.href = link.href; }, link);
    });

    document.querySelectorAll('.js-logout-trigger').forEach(button => {
        button.addEventListener('click', event => {
            if (!isProfileDirty()) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            openUnsavedModal(() => document.querySelector('#logoutModal form')?.requestSubmit(), button);
        });
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && unsavedModal && !unsavedModal.hidden) closeUnsavedModal();
    });

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
        const restoring = avatarAction !== 'none';
        const title = document.getElementById('profileAvatarRemoveTitle');
        const text = document.getElementById('profileAvatarRemoveText');
        const confirm = document.getElementById('profileAvatarRemoveConfirmBtn');
        if (restoring) {
            if (title) title.textContent = '¿Deseas restaurar tu fotografía de perfil?';
            if (text) text.textContent = 'Se descartará el cambio pendiente y se conservará la fotografía guardada.';
            if (confirm) confirm.textContent = 'Restaurar fotografía';
        } else {
            if (title) title.textContent = '¿Deseas eliminar tu fotografía de perfil?';
            if (text) text.textContent = 'Se volverá a mostrar la inicial de tu nombre.';
            if (confirm) confirm.textContent = 'Eliminar fotografía';
        }
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
    editSaveBtn?.addEventListener('click', () => {
        if (!loadedRawImage || !avatarFileInput) return;

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

        canvas.toBlob((blob) => {
            if (!blob) {
                showAlert('No fue posible procesar la imagen.', 'error');
                editSaveBtn.disabled = false;
                editCancelBtn.disabled = false;
                editSaveBtn.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i> Usar fotografía';
                return;
            }

            pendingAvatarFile = new File([blob], 'avatar.jpg', { type: 'image/jpeg' });
            avatarAction = 'replace';
            if (avatarRemovePendingInput) avatarRemovePendingInput.value = '0';
            const transfer = new DataTransfer();
            transfer.items.add(pendingAvatarFile);
            avatarFileInput.files = transfer.files;
            const previousPreviewUrl = pendingAvatarPreviewUrl;
            pendingAvatarPreviewUrl = URL.createObjectURL(blob);
            updateAvatarDOM(pendingAvatarPreviewUrl);
            if (previousPreviewUrl) URL.revokeObjectURL(previousPreviewUrl);
            syncSubmitState();
            closeEditModal();
            hideAlert();
            editSaveBtn.disabled = false;
            editCancelBtn.disabled = false;
            editSaveBtn.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i> Usar fotografía';
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

    removeConfirmBtn?.addEventListener('click', () => {
        if (avatarAction !== 'none') {
            pendingAvatarFile = null;
            avatarAction = 'none';
            if (avatarFileInput) avatarFileInput.value = '';
            if (avatarRemovePendingInput) avatarRemovePendingInput.value = '0';
            updateAvatarDOM(originalAvatarUrl);
            if (pendingAvatarPreviewUrl) {
                URL.revokeObjectURL(pendingAvatarPreviewUrl);
                pendingAvatarPreviewUrl = null;
            }
            syncSubmitState();
            closeRemoveModal();
            hideAlert();
            return;
        }
        if (!originalHasAvatar) {
            closeRemoveModal();
            return;
        }
        pendingAvatarFile = null;
        avatarAction = 'remove';
        if (avatarFileInput) avatarFileInput.value = '';
        if (avatarRemovePendingInput) avatarRemovePendingInput.value = '1';
        updateAvatarDOM(null);
        if (pendingAvatarPreviewUrl) {
            URL.revokeObjectURL(pendingAvatarPreviewUrl);
            pendingAvatarPreviewUrl = null;
        }
        syncSubmitState();
        closeRemoveModal();
        hideAlert();
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
        const avatarUrl = rawAvatarUrl
            ? (rawAvatarUrl.startsWith('blob:')
                ? rawAvatarUrl
                : `${rawAvatarUrl}${rawAvatarUrl.includes('?') ? '&' : '?'}t=${timestamp}`)
            : null;

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
        if (avatarRemoveBtn) {
            avatarRemoveBtn.hidden = !originalHasAvatar && avatarAction === 'none';
            avatarRemoveBtn.innerHTML = avatarAction === 'none'
                ? '<i class="fa-solid fa-trash-can" aria-hidden="true"></i> Eliminar foto'
                : '<i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Restaurar foto';
        }

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
