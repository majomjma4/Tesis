console.log("Módulo de proyectos inicializado correctamente.");

// Inicio de selección de elementos
const projectsSkeleton = document.querySelector("#projectsSkeleton");
const projectsContent = document.querySelector("#projectsContent");
const uploadForm = document.querySelector("#uploadDocumentForm");
const deliveryPhase = document.querySelector("#deliveryPhase");
const academicFileInput = document.querySelector("#academicFile");
const fileDragZone = document.querySelector("#fileDragZone");
const uploadFormAlert = document.querySelector("#uploadFormAlert");
const fileErrorText = document.querySelector("#fileErrorText");
// Final de selección de elementos

let selectedFile = null;

// Inicio de simulación de carga (Skeleton Loader)
setTimeout(() => {
    if (projectsSkeleton) {
        projectsSkeleton.style.display = "none";
        projectsSkeleton.setAttribute("hidden", "");
    }
    if (projectsContent) {
        projectsContent.style.display = "grid";
        const hashTarget = window.location.hash ? document.querySelector(window.location.hash) : null;
        if (hashTarget) {
            window.setTimeout(() => {
                hashTarget.scrollIntoView({ behavior: "smooth", block: "center" });
                hashTarget.classList.add("project-target-highlight");
                window.setTimeout(() => hashTarget.classList.remove("project-target-highlight"), 2200);
            }, 120);
        }
        // Agregar clase show para animar entrada
        setTimeout(() => {
            const cards = projectsContent.querySelectorAll(
                ".project-card, .timeline-card, .timeline-dot, .reminders-panel, .notifications-panel"
            );
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.classList.add("show");
                }, index * 85);
            });
        }, 50);
    }
}, 800);
// Final de simulación de carga

// Inicio de toggle de observaciones
document.querySelectorAll(".toggle-observations-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
        const wrapper = btn.closest(".timeline-observations-box");
        const content = wrapper?.querySelector(".observations-list-content");
        
        if (!content) return;
        
        const isExpanded = btn.getAttribute("aria-expanded") === "true";
        btn.setAttribute("aria-expanded", String(!isExpanded));
        
        if (isExpanded) {
            content.setAttribute("hidden", "");
        } else {
            content.removeAttribute("hidden");
        }
    });
});
// Final de toggle de observaciones

// Inicio de comportamiento Drag & Drop
if (fileDragZone && academicFileInput) {
    fileDragZone.addEventListener("click", () => {
        academicFileInput.click();
    });

    academicFileInput.addEventListener("change", (e) => {
        if (e.target.files && e.target.files.length > 0) {
            handleFileSelection(e.target.files[0]);
        }
    });

    fileDragZone.addEventListener("dragover", (e) => {
        e.preventDefault();
        fileDragZone.classList.add("dragover");
    });

    ["dragleave", "dragend"].forEach((type) => {
        fileDragZone.addEventListener(type, () => {
            fileDragZone.classList.remove("dragover");
        });
    });

    fileDragZone.addEventListener("drop", (e) => {
        e.preventDefault();
        fileDragZone.classList.remove("dragover");
        
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            handleFileSelection(e.dataTransfer.files[0]);
            academicFileInput.files = e.dataTransfer.files;
        }
    });
}

function handleFileSelection(file) {
    const allowedExtensions = /(\.pdf|\.doc|\.docx|\.zip)$/i;
    const maxSize = 10 * 1024 * 1024; // 10MB
    
    // Reset state
    fileDragZone.closest(".form-group").classList.remove("invalid");
    
    if (!allowedExtensions.exec(file.name)) {
        showFileError("Formato de archivo no válido. Solo se admiten PDF, Word (.doc, .docx) o ZIP.");
        selectedFile = null;
        return;
    }
    
    if (file.size > maxSize) {
        showFileError("El tamaño del archivo supera el límite de 10MB.");
        selectedFile = null;
        return;
    }
    
    selectedFile = file;
    
    // Actualizar vista del dragzone
    const icon = fileDragZone.querySelector(".upload-icon");
    const strong = fileDragZone.querySelector("strong");
    const span = fileDragZone.querySelector("span");
    const small = fileDragZone.querySelector("small");
    
    if (icon) {
        icon.className = "fa-solid fa-file-circle-check upload-icon";
        icon.style.color = "var(--accent)";
    }
    if (strong) strong.innerText = file.name;
    if (span) span.innerText = `(${(file.size / (1024 * 1024)).toFixed(2)} MB)`;
    if (small) small.innerText = "Archivo cargado correctamente. Haz clic para cambiarlo.";
}

function showFileError(message) {
    if (fileErrorText) {
        fileErrorText.innerText = message;
    }
    fileDragZone.closest(".form-group").classList.add("invalid");
}
// Final de comportamiento Drag & Drop

// Inicio de envío de formulario de entrega
uploadForm?.addEventListener("submit", (e) => {
    e.preventDefault();
    
    // Limpiar alertas
    if (uploadFormAlert) {
        uploadFormAlert.style.display = "none";
        uploadFormAlert.className = "upload-alert";
    }
    
    let isValid = true;
    
    // Validar fase
    const phaseGroup = deliveryPhase.closest(".form-group");
    if (!deliveryPhase.value) {
        phaseGroup.classList.add("invalid");
        isValid = false;
    } else {
        phaseGroup.classList.remove("invalid");
    }
    
    // Validar archivo
    const fileGroup = fileDragZone.closest(".form-group");
    if (!selectedFile) {
        if (fileErrorText) fileErrorText.innerText = "Debes seleccionar o arrastrar un archivo académico.";
        fileGroup.classList.add("invalid");
        isValid = false;
    } else {
        fileGroup.classList.remove("invalid");
    }
    
    if (!isValid) return;
    
    // Simular carga y guardado
    const submitBtn = uploadForm.querySelector("button[type='submit']");
    const originalBtnHTML = submitBtn.innerHTML;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando documento...';
    
    setTimeout(() => {
        // Éxito simulado
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnHTML;
        
        if (uploadFormAlert) {
            uploadFormAlert.style.display = "flex";
            uploadFormAlert.classList.add("success");
            uploadFormAlert.querySelector("span").innerText = "¡Entrega registrada con éxito! Se ha generado la Versión 5 de tu proyecto. El tutor académico ha sido notificado para la revisión.";
        }
        
        // Reset del formulario
        uploadForm.reset();
        selectedFile = null;
        
        // Reset de la zona de arrastre
        const icon = fileDragZone.querySelector(".upload-icon");
        const strong = fileDragZone.querySelector("strong");
        const span = fileDragZone.querySelector("span");
        const small = fileDragZone.querySelector("small");
        
        if (icon) {
            icon.className = "fa-solid fa-file-arrow-up upload-icon";
            icon.style.color = "var(--blue)";
        }
        if (strong) strong.innerText = "Arrastra tu archivo aquí";
        if (span) span.innerText = "o haz clic para explorar";
        if (small) small.innerText = "Formatos admitidos: PDF, Word, ZIP (Máx. 10MB)";
        
        // Hacer scroll suave hacia arriba para que se vea el mensaje de éxito
        uploadForm.closest(".reminders-panel").scrollIntoView({ behavior: "smooth" });
        
    }, 1500);
});

// Limpiar estados inválidos al interactuar
deliveryPhase?.addEventListener("change", () => {
    deliveryPhase.closest(".form-group").classList.remove("invalid");
});
// Final de envío de formulario de entrega
