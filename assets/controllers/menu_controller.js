import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
  static targets = ["panel", "button"];

  connect() {
    // ferme par défaut
    this.isOpen = false;
    this.panelTarget.style.maxHeight = "0px";
    this.buttonTarget.setAttribute("aria-expanded", "false");
    this.panelTarget.setAttribute("aria-hidden", "true");
  }

  toggle() {
    this.isOpen = !this.isOpen;
    this.buttonTarget.setAttribute("aria-expanded", String(this.isOpen));
    this.panelTarget.setAttribute("aria-hidden", String(!this.isOpen));

    if (this.isOpen) {
      // calcule la hauteur auto pour l’animation
      this.panelTarget.style.maxHeight = this.panelTarget.scrollHeight + "px";
    } else {
      this.panelTarget.style.maxHeight = "0px";
    }
  }
}
