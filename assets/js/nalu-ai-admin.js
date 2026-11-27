/**
 * Settings > Nalu AI:
 * - "Add another agent" appends an empty row to the More agents table.
 * - "Test connection" runs in place through admin-ajax.php and shows the result under the button.
 * Without this script both still work: saving adds a fresh empty row, and the test form posts
 * to admin-post.php and reloads the page with the result.
 */
(function () {
    'use strict';

    var button = document.querySelector('[data-nalu-ai-add-agent]');
    var rows = document.querySelector('[data-nalu-ai-agent-rows]');
    var template = document.querySelector('[data-nalu-ai-agent-row-template]');

    if (!button || !rows || !template) {
        return;
    }

    var nextIndex = parseInt(rows.getAttribute('data-next-index'), 10) || rows.children.length;

    button.hidden = false;
    button.addEventListener('click', function () {
        var html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
        nextIndex += 1;

        var holder = document.createElement('tbody');
        holder.innerHTML = html.trim();

        var row = holder.querySelector('tr');

        if (!row) {
            return;
        }

        rows.appendChild(row);

        var firstInput = row.querySelector('input');

        if (firstInput) {
            firstInput.focus();
        }
    });
})();

(function () {
    'use strict';

    var form = document.querySelector('[data-nalu-ai-test-connection]');
    var output = document.querySelector('[data-nalu-ai-test-result]');

    if (!form || !output || !window.fetch || !window.FormData) {
        return;
    }

    var button = form.querySelector('[type="submit"]');
    var spinner = form.querySelector('.spinner');

    function showResult(result) {
        var notice = document.createElement('div');
        notice.className = 'notice inline notice-' + (result.ok ? 'success' : 'error');

        var message = document.createElement('p');
        message.textContent = result.message;
        notice.appendChild(message);

        if (result.details) {
            var details = document.createElement('p');
            var code = document.createElement('code');
            code.className = 'nalu-ai-test-details';
            code.textContent = result.details;
            details.appendChild(code);
            notice.appendChild(details);
        }

        output.replaceChildren(notice);
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (button.disabled) {
            return;
        }

        button.disabled = true;
        spinner.classList.add('is-active');
        output.replaceChildren();

        fetch(form.getAttribute('data-ajax-url'), {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                if (!result || typeof result.message !== 'string') {
                    throw new Error('Unexpected response');
                }

                showResult(result);
            })
            .catch(function () {
                // A non-JSON reply, e.g. an expired nonce ("-1") or a network error.
                showResult({ ok: false, message: form.getAttribute('data-error-message') });
            })
            .finally(function () {
                button.disabled = false;
                spinner.classList.remove('is-active');
            });
    });
})();
