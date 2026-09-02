<?php
$repositoryCardMenuId = (string) ($repositoryCardMenuId ?? 'repositoryCardActionsMenu');
$repositoryCardMenuActions = is_array($repositoryCardMenuActions ?? null) ? $repositoryCardMenuActions : [];
?>
<div class="ed-menu ar-card-action-menu" data-repository-card-menu data-record-menu>
    <button class="ed-action ar-card-menu-trigger" type="button" aria-label="Más acciones" title="Más acciones" aria-haspopup="menu" aria-expanded="false" aria-controls="<?= e($repositoryCardMenuId) ?>" data-repository-card-menu-trigger data-record-menu-trigger>
        <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
        <span class="ar-card-menu-sr-only">Más acciones</span>
    </button>
    <div class="ed-menu-panel" id="<?= e($repositoryCardMenuId) ?>" role="menu" hidden data-record-menu-panel>
        <?php foreach ($repositoryCardMenuActions as $item): ?>
            <?php
            $itemClasses = !empty($item['danger']) ? 'is-danger' : '';
            $itemAction = (string) ($item['action'] ?? '');
            ?>
            <?php if (!empty($item['url']) && !empty($item['enabled'])): ?>
                <a role="menuitem" href="<?= e((string) $item['url']) ?>"<?= !empty($item['download']) ? ' download' : '' ?><?= !empty($item['separator']) ? ' data-menu-separated' : '' ?><?= $itemClasses !== '' ? ' class="' . e($itemClasses) . '"' : '' ?>>
                    <i class="fa-solid <?= e((string) ($item['icon'] ?? 'fa-circle')) ?>" aria-hidden="true"></i><span><?= e((string) ($item['label'] ?? 'Acción')) ?></span>
                </a>
            <?php else: ?>
                <button type="button" role="menuitem"<?= empty($item['enabled']) ? ' disabled' : '' ?><?= !empty($item['hidden']) ? ' hidden' : '' ?><?= !empty($item['separator']) ? ' data-menu-separated' : '' ?><?= $itemClasses !== '' ? ' class="' . e($itemClasses) . '"' : '' ?><?= $itemAction !== '' ? ' data-record-admin-action="' . e($itemAction) . '"' : '' ?>>
                    <i class="fa-solid <?= e((string) ($item['icon'] ?? 'fa-circle')) ?>" aria-hidden="true"></i><span><?= e((string) ($item['label'] ?? 'Acción')) ?></span>
                </button>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
