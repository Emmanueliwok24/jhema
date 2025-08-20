<?php
// admin/partials/logout.php
// *** NO WHITESPACE OR BOM ABOVE THIS LINE ***
?>

<!-- Logout Confirmation Modal -->
<div class="modal fade" id="staticBackdrop" data-bs-backdrop="static" data-bs-keyboard="false"
     tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body">
        <h5 class="modal-title" id="staticBackdropLabel">Logging Out</h5>
        <p>Are you sure you want to log out?</p>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="button-box mt-3">
          <button type="button" class="btn btn--no btn-secondary" data-bs-dismiss="modal">No</button>
          <button type="button" class="btn btn--yes btn-primary" id="logoutConfirmBtn">Yes</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  // Attach logout action
  document.addEventListener("DOMContentLoaded", function () {
    const btn = document.getElementById("logoutConfirmBtn");
    if (btn) {
      btn.addEventListener("click", function () {
        window.location.href = "<?php echo e(base_url('admin/logout.php')); ?>";
      });
    }
  });
</script>
