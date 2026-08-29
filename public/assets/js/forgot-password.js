(() => {
    const cooldownAlert = document.querySelector("[data-cooldown-seconds]");
    if (!cooldownAlert) return;

    const remainingElement = cooldownAlert.querySelector("[data-cooldown-remaining]");
    const unitElement = cooldownAlert.querySelector("[data-cooldown-unit]");
    const messageElement = cooldownAlert.querySelector("[data-cooldown-message]");
    const completeMessage = "Ya puedes intentar solicitar un nuevo enlace.";
    let seconds = Number.parseInt(cooldownAlert.dataset.cooldownSeconds || "", 10);
    let timer = null;

    if (!remainingElement || !unitElement || !messageElement || !Number.isInteger(seconds) || seconds < 1) return;

    const renderRemaining = () => {
        remainingElement.textContent = String(seconds);
        unitElement.textContent = seconds === 1 ? "segundo" : "segundos";
    };

    const finish = () => {
        messageElement.textContent = completeMessage;
        cooldownAlert.setAttribute("role", "status");
        if (timer !== null) window.clearInterval(timer);
    };

    renderRemaining();
    timer = window.setInterval(() => {
        seconds -= 1;
        if (seconds <= 0) {
            finish();
            return;
        }
        renderRemaining();
    }, 1000);
})();
