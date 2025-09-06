// assets/controllers/annonce_search_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['q', 'ville', 'category', 'results'];
  static values = { url: String }

  connect() {
    this.timer = null;
  }

  debounceFetch() {
    if (this.timer) clearTimeout(this.timer);
    this.timer = setTimeout(() => this.fetchResults(), 250); // délai 250ms
  }

  fetchResults() {
    const params = new URLSearchParams();

    if (this.hasQTarget && this.qTarget.value) {
      params.set('q', this.qTarget.value);
    }
    if (this.hasVilleTarget && this.villeTarget.value) {
      params.set('ville', this.villeTarget.value);
    }
    if (this.hasCategoryTarget && this.categoryTarget.value) {
      params.set('category', this.categoryTarget.value);
    }

    fetch(`${this.urlValue}?${params.toString()}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(r => r.text())
      .then(html => {
        this.resultsTarget.innerHTML = html;
      })
      .catch(() => {
        this.resultsTarget.innerHTML = '<p class="text-red-600">Erreur de recherche.</p>';
      });
  }
}
