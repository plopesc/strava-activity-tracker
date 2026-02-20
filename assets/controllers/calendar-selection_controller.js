import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['icon'];

    select(event) {
        this.iconTargets.forEach(icon => {
            icon.classList.remove('ring-2', 'ring-strava-orange', 'scale-125');
        });
        event.currentTarget.classList.add('ring-2', 'ring-strava-orange', 'scale-125');
    }
}
