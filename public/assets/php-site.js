(() => {
  const form = document.querySelector('[data-contact-form-php]');
  const configNode = document.querySelector('[data-plan-config]');
  if (configNode) {
    try {
      const plans = JSON.parse(atob(configNode.dataset.planConfig));
      const result = document.querySelector('.quiz-result');
      if (result) new MutationObserver(() => {
        if (result.hidden) return;
        const name = result.querySelector('[data-result-name]')?.textContent.split(' is ')[0];
        const plan = plans.find(item => item.name === name);
        if (!plan) return;
        result.querySelector('[data-result-price]').textContent = `PKR ${Number(plan.starting_price_pkr).toLocaleString()}${plan.price_suffix || ''}`;
        result.querySelector('[data-result-copy]').textContent = plan.description;
        result.querySelector('[data-result-link]').href = `/contact?plan=${encodeURIComponent(plan.name)}#inquiry`;
      }).observe(result, {attributes: true, attributeFilter: ['hidden']});
    } catch (_) {}
  }
  if (!form) return;
  const status = form.querySelector('.form-status');
  const finePrint = form.querySelector('.fine-print');
  const button = form.querySelector('button[type="submit"]');
  button.textContent = 'Send enquiry ↗';
  if (finePrint) finePrint.textContent = 'Your enquiry will be securely saved and sent to the Nexum team.';
  const trap = document.createElement('input');
  trap.type = 'text'; trap.name = 'website'; trap.tabIndex = -1; trap.autocomplete = 'off'; trap.setAttribute('aria-hidden', 'true'); trap.style.cssText = 'position:absolute;left:-9999px';
  form.append(trap);
  form.addEventListener('submit', async event => {
    event.preventDefault();
    button.disabled = true; status.textContent = 'Sending your enquiry…';
    try {
      const response = await fetch('/api/leads', {method: 'POST', headers: {'Accept': 'application/json'}, body: new FormData(form)});
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.error || 'Unable to send your enquiry.');
      form.reset(); status.textContent = payload.message;
    } catch (error) {
      status.textContent = error.message || 'Unable to send your enquiry. Please try again.';
    } finally { button.disabled = false; }
  });
})();
