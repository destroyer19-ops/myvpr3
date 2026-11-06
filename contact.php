<?php
if (session_status() == PHP_SESSION_NONE) {
    require_once 'includes/session.php';
    session_init();
}
include 'includes/header.php';
?>
<body>
    <?php include 'includes/navbar.php'; ?>

    <section class="hero-section py-5" style="margin-top: 80px;">
        <div class="container text-center">
            <h1 class="display-4 fw-bold">Contact Us</h1>
            <p class="lead">We'd love to hear from you. Get in touch with our team.</p>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-5 mb-5 mb-lg-0">
                    <h2 class="mb-4">Get in Touch</h2>
                    <p class="mb-4">Our dedicated team is here to support you in your spiritual journey and answer any questions you may have about our platform.</p>
                    
                    <div class="d-flex mb-4">
                        <div class="icon-circle bg-primary text-white me-3 p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <h5 class="mb-1">Our Address</h5>
                            <p class="text-muted mb-0">Loveworld City, Asese, Ogun State, Nigeria</p>
                        </div>
                    </div>

                    <div class="d-flex mb-4">
                        <div class="icon-circle bg-success text-white me-3 p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div>
                            <h5 class="mb-1">Email Us</h5>
                            <p class="text-muted mb-0">support@myvpr.org</p>
                            <p class="text-muted mb-0">info@myvpr.org</p>
                        </div>
                    </div>

                    <div class="d-flex">
                        <div class="icon-circle bg-info text-white me-3 p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <h5 class="mb-1">Support Hours</h5>
                            <p class="text-muted mb-0">Monday - Friday: 9am - 5pm</p>
                            <p class="text-muted mb-0">Sunday: Active Support during Crusades</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card p-4 border-0 shadow-lg">
                        <h2 class="mb-4">Send a Message</h2>
                        <form action="#" method="POST">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Your Name</label>
                                    <input type="text" class="form-control" placeholder="John Doe" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Your Email</label>
                                    <input type="email" class="form-control" placeholder="john@example.com" required>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Subject</label>
                                    <input type="text" class="form-control" placeholder="How can we help?" required>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Message</label>
                                    <textarea class="form-control" rows="5" placeholder="Tell us more about your inquiry..." required></textarea>
                                </div>
                                <div class="col-md-12 mt-4">
                                    <button type="submit" class="btn btn-primary-custom w-100 py-3">Send Message</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                    <p class="mb-0">&copy; 2026 Virtual Praise Room. All Rights Reserved.</p>
                    <p class="small text-muted">Loveworld City, Asese, Ogun State, Nigeria</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <ul class="list-inline footer-links">
                        <li class="list-inline-item"><a href="#">Privacy Policy</a></li>
                        <li class="list-inline-item"><a href="#">Terms of Service</a></li>
                        <li class="list-inline-item"><a href="about.php">About Us</a></li>
                        <li class="list-inline-item"><a href="contact.php">Contact</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <?php include 'includes/bottom_navbar.php'; ?>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
