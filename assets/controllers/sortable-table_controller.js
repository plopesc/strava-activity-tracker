import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['body'];

    sort(event) {
        const col = parseInt(event.currentTarget.dataset.col, 10);
        const tbody = this.bodyTarget;
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const isAsc = event.currentTarget.dataset.direction !== 'asc';
        event.currentTarget.dataset.direction = isAsc ? 'asc' : 'desc';

        rows.sort((a, b) => {
            const aCell = a.children[col];
            const bCell = b.children[col];
            const aRaw = aCell.dataset.sortValue;
            const bRaw = bCell.dataset.sortValue;
            const aVal = aRaw !== undefined ? parseFloat(aRaw) : aCell.textContent.trim();
            const bVal = bRaw !== undefined ? parseFloat(bRaw) : bCell.textContent.trim();

            if (typeof aVal === 'number' && !isNaN(aVal) && typeof bVal === 'number' && !isNaN(bVal)) {
                return isAsc ? aVal - bVal : bVal - aVal;
            }
            return isAsc
                ? String(aVal).localeCompare(String(bVal))
                : String(bVal).localeCompare(String(aVal));
        });

        rows.forEach(row => tbody.appendChild(row));
    }
}
