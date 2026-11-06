<?php
ini_set('session.use_only_cookies', 1);
if (session_status() == PHP_SESSION_NONE) {
    require_once 'includes/session.php';
    session_init();
}

$crusade_code = isset($_GET['crusade_code']) ? $_GET['crusade_code'] : '';
?>
<?php include 'includes/header.php'; ?>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container py-5 page-container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card text-center">
                    <div class="card-header">
                        <h2>Give Your Life to Christ</h2>
                    </div>
                    <div class="card-body">
                        <p class="lead">
                            We invite you to make Jesus Christ the Lord of your life by praying this simple prayer now!
                        </p>
                        
                        <!-- Video Placeholder -->
                        <div class="my-4">
                            <video controls style="width: 100%;">
                                <source src="https://worshiplyrics.s3.amazonaws.com/filler.mp4" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        </div>

                        <div class="prayer-box my-4 p-4 rounded bg-light text-dark">
                            <p>“O Lord God, I believe with all my heart in Jesus Christ, Son of the living God. I believe He died for me and God raised Him from the dead. I believe He’s alive today. I confess with my mouth that Jesus Christ is the Lord of my life from this day. Through Him and in His Name, I have eternal life; I’m born again. Thank you Lord, for saving my soul! I’m now a child of God. Hallelujah!”</p>
                        </div>
                        <p class="lead">
                            Congratulations! You are now a child of God. We want to send you ministry resources gift pack to help you grow as a Christian.
                        </p>
                        <p class="lead">
                            Kindly click the button below to record your decision and get your gift pack.
                        </p>
                        <a href="log_salvation_decision.php?crusade_code=<?php echo htmlspecialchars($crusade_code); ?>" class="btn btn-primary btn-lg mt-3">
                            If you said this prayer we have a gift for you
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/bottom_navbar.php'; ?>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
