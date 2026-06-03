

document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.btn-resend-opt-in').forEach(function (button) {
    const uri = button.getAttribute('data-uri');
    if (!uri) {
      return;
    }

    button.addEventListener('click', function (event) {
      event.preventDefault();

      // Derive the email from the form input that's still in the DOM right
      // next to the validation error, so the address never has to be rendered
      // into the HTML (which would survive in browser history and view-source).
      const form = button.closest('form');
      const emailInput = form ? form.querySelector('input[type="email"]') : null;
      const email = emailInput ? emailInput.value : '';

      const formData = new FormData();
      formData.append('email', email);

      // The endpoint always returns a uniform { success: true } regardless of
      // whether a mail was actually sent — never leak per-email state.
      fetch(uri, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      }).catch(function () {
        // Network failure is the only condition worth handling; swallow the
        // rest to avoid surfacing endpoint internals in the console.
      });
    });
  });
});
