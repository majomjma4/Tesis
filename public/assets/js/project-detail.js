(() => {
    const activeTab = document.querySelector('.detail-tabs a.is-active');
    const panel = document.querySelector('.detail-panel');
    if (activeTab && panel) panel.setAttribute('aria-labelledby', activeTab.textContent.trim());
})();
