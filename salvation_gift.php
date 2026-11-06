<?php
ini_set('session.use_only_cookies', 1);
if (session_status() == PHP_SESSION_NONE) {
    require_once 'includes/session.php';
    session_init();
}
?>
<?php include 'includes/header.php'; ?>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container py-5 page-container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card text-center">
                    <div class="card-header">
                        <h2>Congratulations on Your Decision!</h2>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['error_message'])): ?>
                            <div class="alert alert-danger text-center">
                                <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                            </div>
                            <?php unset($_SESSION['error_message']); ?>
                        <?php endif; ?>
                        <p class="lead">
                            We rejoice with you on your decision to give your life to Christ! As a new child of God, we have a special gift for you to help you grow in your faith.
                        </p>
                        <p class="lead">
                            You will receive a free copy of "Now That You Are Born Again".
                        </p>
                        <p class="lead">
                            Please provide your details below so we can send you your gift pack.
                        </p>
                        <!-- Form for user details would go here -->
                        <form action="submit_gift_request.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                            <div class="mb-3">
                                <label for="name" class="form-label">Your Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Your Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <button type="submit" class="btn btn-success btn-lg mt-3">Claim Your Gift!</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/bottom_navbar.php'; ?>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
