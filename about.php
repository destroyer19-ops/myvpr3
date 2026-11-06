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
            <h1 class="display-4 fw-bold">About Virtual Praise Room</h1>
            <p class="lead">Connecting the world through praise and worship.</p>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <h2 class="mb-4">Our Mission</h2>
                    <p>Virtual Praise Room (VPR) is a premier digital platform dedicated to fostering spiritual connection and community engagement. Our mission is to provide a seamless, high-quality environment where individuals and faith-based organizations can gather for worship, regardless of geographical boundaries.</p>
                    
                    <h2 class="mt-5 mb-4">Our Business Activities</h2>
                    <p>We specialize in providing technological solutions for the modern spiritual landscape. Our activities include:</p>
                    <ul>
                        <li><strong>Platform Development:</strong> Continuous improvement of our virtual meeting and streaming infrastructure.</li>
                        <li><strong>Community Management:</strong> Facilitating a safe and inspiring space for testimony sharing and spiritual growth.</li>
                        <li><strong>Content Curation:</strong> Maintaining a robust library of spiritual and educational video content.</li>
                        <li><strong>Support Services:</strong> Providing technical and spiritual support to our global user base.</li>
                    </ul>

                    <h2 class="mt-5 mb-4">Our Services</h2>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="card h-100 p-4 border-0 shadow-sm">
                                <h5>Virtual Worship Rooms</h5>
                                <p>Host and participate in real-time video and audio worship sessions with features like screen sharing and live chat.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100 p-4 border-0 shadow-sm">
                                <h5>Live Crusades</h5>
                                <p>Scalable streaming solutions for large-scale religious events and community outreach programs.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100 p-4 border-0 shadow-sm">
                                <h5>Video Library</h5>
                                <p>Access a vast archive of recorded praise sessions, sermons, and inspirational content on-demand.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100 p-4 border-0 shadow-sm">
                                <h5>Testimony Platform</h5>
                                <p>A dedicated space for community members to share and celebrate their personal journeys of faith.</p>
                            </div>
                        </div>
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
