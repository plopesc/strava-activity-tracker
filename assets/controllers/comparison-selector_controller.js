import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['checkbox', 'compareButton'];
    static values = { selected: { type: Array, default: [] } };

    connect() {
        this.updateButton();
    }

    toggle(event) {
        const id = event.currentTarget.value;
        if (event.currentTarget.checked) {
            this.selectedValue = [...this.selectedValue, id];
        } else {
            this.selectedValue = this.selectedValue.filter(v => v !== id);
        }
        this.updateButton();
    }

    updateButton() {
        if (!this.hasCompareButtonTarget) return;
        const btn = this.compareButtonTarget;
        btn.disabled = this.selectedValue.length < 2;
        btn.textContent = `Compare (${this.selectedValue.length})`;
    }

    compare() {
        const ids = this.selectedValue.join(',');
        window.location.href = `/activities/compare?ids=${ids}`;
    }
}
