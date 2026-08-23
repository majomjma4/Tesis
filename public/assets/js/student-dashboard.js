(function () {
    'use strict';

    function setFocusableState(container, active) {
        container.querySelectorAll('a, button, [tabindex]').forEach(function (element) {
            if (!element.hasAttribute('data-project-tabindex')) {
                element.setAttribute('data-project-tabindex', element.getAttribute('tabindex') || '');
            }
            if (active) {
                var original = element.getAttribute('data-project-tabindex');
                if (original === '') element.removeAttribute('tabindex');
                else element.setAttribute('tabindex', original);
            } else {
                element.setAttribute('tabindex', '-1');
            }
        });
    }

    function initProjectCarousel(carousel) {
        var cards = Array.from(carousel.querySelectorAll('[data-project-index]'));
        var previous = carousel.querySelector('[data-project-prev]');
        var next = carousel.querySelector('[data-project-next]');
        var indicator = carousel.querySelector('[data-project-indicator]');
        var count = cards.length;
        var current = Number.parseInt(carousel.getAttribute('data-initial-index') || '0', 10);
        current = Number.isFinite(current) ? Math.max(0, Math.min(current, count - 1)) : 0;
        if (!count) return;

        var groups = [
            Array.from(document.querySelectorAll('[data-project-dependent-group="observations"] [data-project-index]')),
            Array.from(document.querySelectorAll('[data-project-dependent-group="delivery"] [data-project-index]')),
            Array.from(document.querySelectorAll('[data-project-dependent-group="period"] [data-project-index]'))
        ];

        function activate(items, index) {
            items.forEach(function (item) {
                var active = Number(item.getAttribute('data-project-index')) === index;
                item.hidden = !active;
                item.setAttribute('aria-hidden', String(!active));
                item.inert = !active;
                item.classList.toggle('is-project-dependent-entering', active);
                setFocusableState(item, active);
            });
        }

        function updateHistory(index) {
            if (!window.history || !window.history.replaceState) return;
            var url = new URL(window.location.href);
            url.searchParams.set('project_index', String(index));
            window.history.replaceState(window.history.state, '', url.toString());
        }

        function render(index, direction) {
            current = Math.max(0, Math.min(index, count - 1));
            cards.forEach(function (card, cardIndex) {
                var active = cardIndex === current;
                card.hidden = !active;
                card.setAttribute('aria-hidden', String(!active));
                card.inert = !active;
                setFocusableState(card, active);
                card.classList.toggle('is-entering-next', active && direction > 0);
                card.classList.toggle('is-entering-prev', active && direction < 0);
            });
            groups.forEach(function (items) { activate(items, current); });
            if (previous) {
                previous.disabled = current === 0;
                previous.setAttribute('aria-disabled', String(previous.disabled));
            }
            if (next) {
                next.disabled = current === count - 1;
                next.setAttribute('aria-disabled', String(next.disabled));
            }
            if (indicator) indicator.textContent = 'Proyecto ' + (current + 1) + ' de ' + count;
            updateHistory(current);
        }

        if (previous) previous.addEventListener('click', function () { if (current > 0) render(current - 1, -1); });
        if (next) next.addEventListener('click', function () { if (current < count - 1) render(current + 1, 1); });
        render(current, 0);
    }

    document.querySelectorAll('[data-student-project-carousel]').forEach(initProjectCarousel);

    document.querySelectorAll('[data-student-carousel]').forEach(function (carousel) {
        var track = carousel.querySelector('.student-carousel__track');
        var previous = carousel.querySelector('[data-carousel-prev]');
        var next = carousel.querySelector('[data-carousel-next]');
        if (!track || !previous || !next) return;

        function cardStep() {
            var card = track.querySelector('.student-resource-card');
            if (!card) return 0;
            var styles = window.getComputedStyle(track);
            return card.getBoundingClientRect().width + (parseFloat(styles.columnGap || styles.gap) || 0);
        }

        function updateControls() {
            var max = Math.max(0, track.scrollWidth - track.clientWidth);
            var canScroll = max > 1;
            previous.disabled = !canScroll || track.scrollLeft <= 1;
            next.disabled = !canScroll || track.scrollLeft >= max - 1;
            previous.hidden = !canScroll;
            next.hidden = !canScroll;
            previous.setAttribute('aria-disabled', String(previous.disabled));
            next.setAttribute('aria-disabled', String(next.disabled));
        }

        function moveByCard(direction) {
            var step = cardStep();
            if (!step) return;
            track.scrollBy({ left: direction * step, behavior: 'smooth' });
            window.setTimeout(updateControls, 350);
        }

        previous.addEventListener('click', function () { moveByCard(-1); });
        next.addEventListener('click', function () { moveByCard(1); });
        track.addEventListener('scroll', updateControls, { passive: true });
        window.addEventListener('resize', updateControls);
        updateControls();
    });
}());
