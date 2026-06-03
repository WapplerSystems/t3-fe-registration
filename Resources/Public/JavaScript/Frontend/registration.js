

document.addEventListener('DOMContentLoaded', function () {
  const resendOptInButton = document.querySelector('.btn-resend-opt-in');

  if (!resendOptInButton) {
    return;
  }

  const uri = resendOptInButton.getAttribute('data-uri');

  resendOptInButton.addEventListener('click', function (event) {
    event.preventDefault();

    // Derive the email from the form input that's still in the DOM right
    // next to the validation error, so the address never has to be rendered
    // into the HTML (which would survive in browser history and view-source).
    const form = resendOptInButton.closest('form');
    const emailInput = form ? form.querySelector('input[type="email"]') : null;
    const email = emailInput ? emailInput.value : '';

    const formData = new FormData();
    formData.append('email', email);

    fetch(uri, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
    })
      .then(response => response.json())
      .then(data => {
        // The endpoint returns a uniform { success: true } regardless of
        // whether a mail was actually sent — never leak per-email state.
        console.log(data);
      })
      .catch(error => {
        console.error('Error:', error);
      });
  });
});
