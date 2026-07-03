// Inicio de configuracion del auto refresh de desarrollo
const devReloadScript = document.currentScript;
const devReloadEndpoint = devReloadScript?.dataset.endpoint;
let devReloadStamp = null;
// Final de configuracion del auto refresh de desarrollo

// Inicio de verificacion de cambios en archivos del proyecto
async function checkProjectChanges() {
    if (!devReloadEndpoint) {
        return;
    }

    try {
        const response = await fetch(`${devReloadEndpoint}&_=${Date.now()}`, {
            cache: "no-store",
        });
        const data = await response.json();

        if (devReloadStamp === null) {
            devReloadStamp = data.stamp;
            return;
        }

        if (data.stamp !== devReloadStamp) {
            window.location.reload();
        }
    } catch (error) {
        console.warn("No se pudo comprobar cambios del proyecto.", error);
    }
}
// Final de verificacion de cambios en archivos del proyecto

// Inicio del ciclo de comprobacion automatica
setInterval(checkProjectChanges, 1000);
checkProjectChanges();
// Final del ciclo de comprobacion automatica
