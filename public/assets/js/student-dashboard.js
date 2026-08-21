(function () {
    'use strict';

    document.querySelectorAll('[data-student-carousel]').forEach(function (carousel) {
        var viewport = carousel.querySelector('.student-carousel__viewport');
        var track = carousel.querySelector('.student-carousel__track');
        var previous = carousel.querySelector('[data-carousel-prev]');
        var next = carousel.querySelector('[data-carousel-next]');
        if (!viewport || !track || !previous || !next) return;

        function cardStep() {
            var card = track.querySelector('.student-resource-card');
            if (!card) return 0;
            var styles = window.getComputedStyle(track);
            return card.getBoundingClientRect().width + (parseFloat(styles.columnGap || styles.gap) || 0);
        }

        function updateControls(align) {
            var max = Math.max(0, track.scrollWidth - viewport.clientWidth);
            var step = cardStep();
            if (align && step > 0 && max > 1) {
                var aligned = Math.min(max, Math.round(track.scrollLeft / step) * step);
                if (Math.abs(track.scrollLeft - aligned) > 1) track.scrollTo({ left: aligned, behavior: 'auto' });
            } else if (track.scrollLeft > 0) {
                if (max <= 1) track.scrollTo({ left: 0, behavior: 'auto' });
            }
            previous.disabled = track.scrollLeft <= 1;
            next.disabled = track.scrollLeft >= max - 1;
            var canScroll = max > 1;
            previous.hidden = !canScroll;
            next.hidden = !canScroll;
        }

        function moveByCard(direction) {
            var step = cardStep();
            var max = Math.max(0, track.scrollWidth - viewport.clientWidth);
            if (!step || max <= 1) return;
            var target = Math.round(track.scrollLeft / step) * step + (direction * step);
            track.scrollTo({ left: Math.max(0, Math.min(max, target)), behavior: 'smooth' });
        }

        previous.addEventListener('click', function () { moveByCard(-1); });
        next.addEventListener('click', function () { moveByCard(1); });
        track.addEventListener('scroll', updateControls, { passive: true });
        window.addEventListener('resize', function () { updateControls(true); });
        updateControls();
    });
}());
