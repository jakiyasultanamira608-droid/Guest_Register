document.addEventListener('DOMContentLoaded', function () {
    // Forms marked data-submit-once: disable the submit button after the
    // first click so a double-click cannot send the form twice.
    document.querySelectorAll('form[data-submit-once]').forEach(function (form) {
        form.addEventListener('submit', function () {
            form.querySelectorAll('button[type="submit"]').forEach(function (button) {
                button.dataset.label = button.textContent;
                button.disabled = true;
                button.textContent = 'Saving...';
            });
        });
    });

    // Re-enable the buttons if the user comes back with the Back button
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('form[data-submit-once] button[type="submit"]').forEach(function (button) {
            button.disabled = false;
            if (button.dataset.label) {
                button.textContent = button.dataset.label;
            }
        });
    });

    // Print buttons (reports)
    document.querySelectorAll('[data-print]').forEach(function (button) {
        button.addEventListener('click', function () {
            window.print();
        });
    });

    // Opens and closes the sidebar on small screens.
    var toggle = document.querySelector('[data-menu-toggle]');

    if (!toggle) {
        return;
    }

    toggle.addEventListener('click', function () {
        var open = document.body.classList.toggle('menu-open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && document.body.classList.contains('menu-open')) {
            document.body.classList.remove('menu-open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.focus();
        }
    });
});
