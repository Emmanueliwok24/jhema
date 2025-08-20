<!-- Customer Login Form -->
<div
  class="aside aside_right overflow-hidden customer-forms"
  id="customerForms"
>
  <div class="customer-forms__wrapper d-flex position-relative">
    <div class="customer__login">
      <div class="aside-header d-flex align-items-center">
        <h3 class="text-uppercase fs-6 mb-0">Login</h3>
        <button class="btn-close-lg js-close-aside ms-auto"></button>
      </div>
      <!-- /.aside-header -->

      <form
        action=""
        method="POST"
        class="aside-content"
      >
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="login">
        <?php if (!empty($_GET['redirect'])): ?>
          <input type="hidden" name="redirect" value="<?= htmlspecialchars($_GET['redirect']) ?>">
        <?php endif; ?>

        <?php if (!empty($loginErrors)): ?>
          <div class="alert alert-danger">
            <?= implode('<br>', array_map('htmlspecialchars', $loginErrors)) ?>
          </div>
        <?php endif; ?>

        <div class="form-floating mb-3">
          <input
            name="email"
            type="email"
            class="form-control form-control_gray"
            id="loginEmailInput"
            placeholder="you@example.com"
            required
            autocomplete="email"
            value="<?= isset($_POST['action'], $_POST['email']) && $_POST['action']==='login' ? htmlspecialchars((string)$_POST['email']) : '' ?>"
          >
          <label for="loginEmailInput">Email address *</label>
        </div>

        <div class="pb-3"></div>

        <div class="form-label-fixed mb-3">
          <label for="loginPasswordInput" class="form-label">Password *</label>
          <input
            name="password"
            id="loginPasswordInput"
            class="form-control form-control_gray"
            type="password"
            placeholder="********"
            required
            autocomplete="current-password"
          />
        </div>

        <div class="d-flex align-items-center mb-3 pb-2">
          <div class="form-check mb-0">
            <input
              name="remember"
              class="form-check-input form-check-input_fill"
              type="checkbox"
              value="1"
              id="loginRememberCheck"
              <?= (isset($_POST['remember']) && $_POST['remember'] === '1') ? 'checked' : '' ?>
            />
            <label
              class="form-check-label text-secondary"
              for="loginRememberCheck"
              >Remember me</label
            >
          </div>
          <a href="<?= htmlspecialchars(base_url('account/request_reset.php')) ?>" class="btn-text ms-auto"
            >Lost password?</a
          >
        </div>

        <button class="btn btn-primary w-100 text-uppercase" type="submit">
          Log In
        </button>

        <div class="customer-option mt-4 text-center">
          <span class="text-secondary">No account yet?</span>
          <a
            href="<?= htmlspecialchars(base_url('account/auth.php')) ?>"
            class="btn-text js-show-register"
            >Create Account</a
          >
        </div>
      </form>
    </div>
    <!-- /.customer__login -->

    <div class="customer__register">
      <div class="aside-header d-flex align-items-center">
        <h3 class="text-uppercase fs-6 mb-0">Create an account</h3>
        <button
          class="btn-close-lg js-close-aside btn-close-aside ms-auto"
        ></button>
      </div>
      <!-- /.aside-header -->

      <form
        action=""
        method="POST"
        class="aside-content"
      >
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="register">
        <?php if (!empty($_GET['redirect'])): ?>
          <input type="hidden" name="redirect" value="<?= htmlspecialchars($_GET['redirect']) ?>">
        <?php endif; ?>

        <?php if (!empty($registerErrors)): ?>
          <div class="alert alert-danger">
            <?= implode('<br>', array_map('htmlspecialchars', $registerErrors)) ?>
          </div>
        <?php endif; ?>

        <div class="form-floating mb-4">
          <input
            name="email"
            type="email"
            class="form-control form-control_gray"
            id="registerEmailInput"
            placeholder="you@example.com"
            required
            autocomplete="email"
            value="<?= isset($_POST['action'], $_POST['email']) && $_POST['action']==='register' ? htmlspecialchars((string)$_POST['email']) : '' ?>"
          />
          <label for="registerEmailInput">Email address *</label>
        </div>

        <div class="pb-1"></div>

        <div class="form-label-fixed mb-4">
          <label for="registerPasswordInput" class="form-label">Password *</label>
          <input
            name="password"
            id="registerPasswordInput"
            class="form-control form-control_gray"
            type="password"
            placeholder="********"
            required
            autocomplete="new-password"
          />
        </div>

        <p class="text-secondary mb-4">
          Your personal data will be used to support your experience throughout this website, to manage access to your account, and for other purposes described in our privacy policy.
        </p>

        <button class="btn btn-primary w-100 text-uppercase" type="submit">
          Register
        </button>

        <div class="customer-option mt-4 text-center">
          <span class="text-secondary">Already have account?</span>
          <a href="#" class="btn-text js-show-login">Login</a>
        </div>
      </form>
    </div>
    <!-- /.customer__register -->
  </div>
  <!-- /.customer-forms__wrapper -->
</div>
<!-- /.aside aside_right -->
