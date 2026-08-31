(function () {
    'use strict';

    document.querySelectorAll('[data-teacher-projects-carousel]').forEach(function (carousel) {
        var viewport = carousel.querySelector('.teacher-projects-carousel__viewport');
        var track = carousel.querySelector('[data-carousel-track]');
        var previous = carousel.querySelector('[data-carousel-prev]');
        var next = carousel.querySelector('[data-carousel-next]');
        var navigation = carousel.querySelector('[data-carousel-navigation]');
        var status = carousel.querySelector('[data-carousel-status]');
        var cardSelector = carousel.getAttribute('data-carousel-card-selector') || '.teacher-project-card';
        var cards = track ? Array.from(track.querySelectorAll(cardSelector)) : [];
        if (!viewport || !track || cards.length < 2 || !previous || !next || !navigation) return;

        function measurements() {
            var styles = window.getComputedStyle(track);
            var gap = parseFloat(styles.columnGap || styles.gap) || 0;
            var cardWidth = cards[0].getBoundingClientRect().width;
            return {
                step: cardWidth + gap,
                visible: Math.max(1, Math.round((viewport.clientWidth + gap) / (cardWidth + gap))),
                max: Math.max(0, track.scrollWidth - viewport.clientWidth)
            };
        }

        function updateControls(align) {
            var size = measurements();
            if (align && size.step > 0) {
                var aligned = Math.min(size.max, Math.round(track.scrollLeft / size.step) * size.step);
                if (Math.abs(track.scrollLeft - aligned) > 1) track.scrollTo({ left: aligned, behavior: 'auto' });
            } else if (size.max <= 1 && track.scrollLeft > 0) {
                track.scrollTo({ left: 0, behavior: 'auto' });
            }

            var first = size.step > 0 ? Math.round(track.scrollLeft / size.step) : 0;
            var last = Math.min(cards.length, first + size.visible);
            var canScroll = size.max > 1;
            navigation.hidden = !canScroll;
            previous.disabled = !canScroll || track.scrollLeft <= 1;
            next.disabled = !canScroll || track.scrollLeft >= size.max - 1;
            if (status) status.textContent = last + ' de ' + cards.length;
        }

        function move(direction) {
            var size = measurements();
            if (!size.step || size.max <= 1) return;
            var target = Math.round(track.scrollLeft / size.step) * size.step + direction * size.step;
            track.scrollTo({ left: Math.max(0, Math.min(size.max, target)), behavior: 'smooth' });
        }

        previous.addEventListener('click', function () { move(-1); });
        next.addEventListener('click', function () { move(1); });
        track.addEventListener('scroll', function () { updateControls(false); }, { passive: true });
        window.addEventListener('resize', function () { updateControls(true); });
        updateControls(false);
    });
}());
