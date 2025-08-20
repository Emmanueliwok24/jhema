
    <!-- Newsletter Popup -->
    <div
      class="modal fade"
      id="newsletterPopup"
      tabindex="-1"
      aria-hidden="true"
    >
      <div class="modal-dialog newsletter-popup modal-dialog-centered">
        <div class="modal-content">
          <button
            type="button"
            class="btn-close"
            data-bs-dismiss="modal"
            aria-label="Close"
          ></button>
          <div class="row p-0 m-0">
            <div class="col-md-6 p-0 d-none d-md-block">
              <div class="newsletter-popup__bg h-100 w-100">
                <img
                  loading="lazy"
                  src="./images/newsletter-popup.jpg"
                  class="h-100 w-100 object-fit-cover d-block"
                  alt=""
                />
              </div>
            </div>
            <div class="col-md-6 p-0 d-flex align-items-center">
              <div class="block-newsletter w-100">
                <h3 class="block__title">Sign Up to Our Newsletter</h3>
                <p>
                  Be the first to get the latest news about trends, promotions,
                  and much more!
                </p>
                <form
  action="<?= htmlspecialchars(rtrim(BASE_URL,'/').'/newsletter/subscribe.php') ?>"
  method="post"
  class="footer-newsletter__form position-relative bg-body js-newsletter-form"
>
  <?php if (function_exists('csrf_token')): ?>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
  <?php endif; ?>
  <input
    class="form-control border-2"
    type="email"
    name="email"
    placeholder="Your email address"
    required
  />
  <input
    class="btn-link fw-medium bg-transparent position-absolute top-0 end-0 h-100"
    type="submit"
    value="JOIN"
  />
</form>
<div class="small mt-2 text-muted js-newsletter-msg" role="status"></div>

              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- /.newsletter-popup position-fixed -->

    <!-- Newsletter Popup -->
<div class="modal fade" id="newsletterPopup" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog newsletter-popup modal-dialog-centered">
    <div class="modal-content">
      <button
        type="button"
        class="btn-close"
        data-bs-dismiss="modal"
        aria-label="Close"
      ></button>
      <div class="row p-0 m-0">
        <div class="col-md-6 p-0 d-none d-md-block">
          <div class="newsletter-popup__bg h-100 w-100">
            <img
              loading="lazy"
              src="./images/newsletter-popup.jpg"
              class="h-100 w-100 object-fit-cover d-block"
              alt=""
            />
          </div>
        </div>
        <div class="col-md-6 p-0 d-flex align-items-center">
          <div class="block-newsletter w-100">
            <h3 class="block__title">Sign Up to Our Newsletter</h3>
            <p>
              Be the first to get the latest news about trends, promotions,
              and much more!
            </p>
            <form
              action="<?= htmlspecialchars(rtrim(BASE_URL,'/').'/newsletter/subscribe.php') ?>"
              method="post"
              class="footer-newsletter__form position-relative bg-body js-newsletter-form"
            >
              <?php if (function_exists('csrf_token')): ?>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
              <?php endif; ?>
              <input
                class="form-control border-2"
                type="email"
                name="email"
                placeholder="Your email address"
                required
              />
              <input
                class="btn-link fw-medium bg-transparent position-absolute top-0 end-0 h-100"
                type="submit"
                value="JOIN"
              />
            </form>
            <div class="small mt-2 js-newsletter-msg" role="status"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- /.newsletter-popup -->

<script>
(function(){
  const form = document.querySelector('.js-newsletter-form');
  if (!form) return;

  const msg = document.querySelector('.js-newsletter-msg');
  const popup = document.getElementById('newsletterPopup');

  function showMessage(text, ok){
    if (!msg) return;
    msg.textContent = text;
    // Nude theme color for success, red for error
    msg.className = 'small mt-2 ' + (ok ? 'text-nude fw-bold' : 'text-danger');
  }

  form.addEventListener('submit', async function(e){
    e.preventDefault();
    const fd = new FormData(form);
    const url = form.getAttribute('action') || '/newsletter/subscribe.php';

    try {
      const r = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' });
      const ct = r.headers.get('content-type') || '';
      let data;

      if (ct.includes('application/json')) {
        data = await r.json();
      } else {
        const txt = await r.text();
        console.warn('Non-JSON response:', txt);
        showMessage('Server returned non-JSON. Check PHP error logs.', false);
        return;
      }

      showMessage(data.msg || (data.ok ? 'Subscribed!' : 'Could not subscribe.'), !!data.ok);

      if (data.ok) {
        form.reset();
        // Close modal after short delay
        setTimeout(() => {
          if (window.bootstrap && popup) {
            const modal = bootstrap.Modal.getInstance(popup) || new bootstrap.Modal(popup);
            modal.hide();
          }
        }, 1200);
      }

    } catch (err) {
      console.error(err);
      showMessage('Network error. Try again.', false);
    }
  });
})();
</script>

<style>
/* Nude theme text */
.text-nude { color: #c19a6b !important; }
</style>
