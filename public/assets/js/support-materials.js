const supportMaterialsSearch = document.querySelector("#supportMaterialsSearch");
const supportMaterialsCategory = document.querySelector("#supportMaterialsCategory");
const supportMaterialCards = [...document.querySelectorAll("[data-support-material]")];
const supportMaterialsCount = document.querySelector("#supportMaterialsCount");
const supportMaterialsEmpty = document.querySelector("#supportMaterialsEmpty");

function normalizeSupportMaterialText(value) {
    return value.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim();
}

function filterSupportMaterials() {
    const search = normalizeSupportMaterialText(supportMaterialsSearch?.value ?? "");
    const category = supportMaterialsCategory?.value ?? "all";
    let visible = 0;
    supportMaterialCards.forEach((card) => {
        const matchesSearch = normalizeSupportMaterialText(card.dataset.search ?? "").includes(search);
        const matchesCategory = category === "all" || card.dataset.category === category;
        card.hidden = !matchesSearch || !matchesCategory;
        if (!card.hidden) visible += 1;
    });
    if (supportMaterialsCount) supportMaterialsCount.textContent = `${visible} ${visible === 1 ? "resultado visible" : "resultados visibles"}`;
    if (supportMaterialsEmpty) supportMaterialsEmpty.hidden = visible !== 0;
}

supportMaterialsSearch?.addEventListener("input", filterSupportMaterials);
supportMaterialsCategory?.addEventListener("change", filterSupportMaterials);
filterSupportMaterials();
