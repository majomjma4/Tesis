(function () {
    'use strict';

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
