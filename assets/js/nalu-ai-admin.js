/**
 * Settings > Nalu AI: "Add another agent" appends an empty row to the More agents table.
 * Without this script the table still works: saving adds a fresh empty row.
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
