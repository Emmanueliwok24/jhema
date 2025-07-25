
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
                  action="./index.html"
                  class="footer-newsletter__form position-relative bg-body"
                >
                  <input
                    class="form-control border-2"
                    type="email"
                    name="email"
                    placeholder="Your email address"
                  />
                  <input
                    class="btn-link fw-medium bg-transparent position-absolute top-0 end-0 h-100"
                    type="submit"
                    value="JOIN"
                  />
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- /.newsletter-popup position-fixed -->