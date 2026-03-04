import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["menu", "openIcon", "closeIcon"];

    toggle() {
        const isHidden = this.menuTarget.classList.contains("hidden");

        if (isHidden) {
            this.menuTarget.classList.remove("hidden");
            this.openIconTarget.classList.add("hidden");
            this.closeIconTarget.classList.remove("hidden");
        } else {
            this.close();
        }
    }

    close() {
        this.menuTarget.classList.add("hidden");
        this.openIconTarget.classList.remove("hidden");
        this.closeIconTarget.classList.add("hidden");
    }
}