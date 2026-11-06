<?php
ini_set('session.use_only_cookies', 1);
if (session_status() == PHP_SESSION_NONE) {
    require_once 'includes/session.php';
    session_init();
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Include database connection
require_once 'includes/db.php';

// Initialize variables
$username = $password = $confirm_password = $email = $kc_handle = $country = $satellite_campus = "";
$city = $region = $church = "";
$username_err = $password_err = $confirm_password_err = $email_err = $satellite_campus_err = $csrf_err = "";
$active_tab = 'individual'; // Default tab

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $csrf_err = "Invalid session. Please refresh and try again.";
    }

    // Preserve the active tab
    $active_tab = $_POST["account_type"] ?? 'individual';

    // Retrieve form fields
    $kc_handle = trim($_POST["kc_handle"] ?? '');
    $country = trim($_POST["country"] ?? '');
    $satellite_campus = trim($_POST["satellite_campus"] ?? '');
    $city = trim($_POST["city"] ?? '');
    $region = trim($_POST["region"] ?? '');
    $church = trim($_POST["church"] ?? '');
    if (empty($csrf_err) && empty($satellite_campus)) {
        $satellite_campus_err = "Please select a satellite campus.";
    }

    // Validate username
    if (empty($csrf_err) && empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } elseif (empty($csrf_err) && !preg_match('/^[a-zA-Z0-9_]+$/', trim($_POST["username"]))) {
        $username_err = "Username can only contain letters, numbers, and underscores.";
    } elseif (empty($csrf_err)) {
        // Prepare a select statement
        $sql = "SELECT id FROM praise_users WHERE username = ?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_username);
            $param_username = trim($_POST["username"]);

            if ($stmt->execute()) {
                $stmt->store_result();

                if ($stmt->num_rows == 1) {
                    $username_err = "This username is already taken.";
                } else {
                    $username = trim($_POST["username"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
                if (function_exists('logDatabaseError')) {
                    logDatabaseError($sql, $conn->error);
                }
            }

            $stmt->close();
        }
    }

    // Validate email
    if (empty($csrf_err) && empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email address.";
    } elseif (empty($csrf_err) && !filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
    } elseif (empty($csrf_err)) {
        // Prepare a select statement
        $sql = "SELECT id FROM praise_users WHERE email = ?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_email);
            $param_email = trim($_POST["email"]);

            if ($stmt->execute()) {
                $stmt->store_result();

                if ($stmt->num_rows == 1) {
                    $email_err = "This email is already registered.";
                } else {
                    $email = trim($_POST["email"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
                if (function_exists('logDatabaseError')) {
                    logDatabaseError($sql, $conn->error);
                }
            }

            $stmt->close();
        }
    }

    // Validate password
    if (empty($csrf_err) && empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";
    } elseif (empty($csrf_err) && strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } elseif (empty($csrf_err)) {
        $password = trim($_POST["password"]);
    }

    // Validate confirm password
    if (empty($csrf_err) && empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";
    } elseif (empty($csrf_err)) {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }

    // Check input errors before inserting in database
    if (empty($csrf_err) && empty($username_err) && empty($password_err) && empty($confirm_password_err) && empty($email_err) && empty($satellite_campus_err)) {

        // Prepare an insert statement
        $sql = "INSERT INTO praise_users (username, email, password, account_type, kc_handle, country, city, region, satellite_campus, church, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssssssssss", $param_username, $param_email, $param_password, $param_account_type, $param_kc_handle, $param_country, $param_city, $param_region, $param_satellite_campus, $param_church);

            // Set parameters
            $param_username = $username;
            $param_email = $email;
            $param_password = password_hash($password, PASSWORD_DEFAULT);
            $param_account_type = $active_tab;
            $param_kc_handle = $kc_handle;
            $param_country = $country;
            $param_city = $city;
            $param_region = $region;
            $param_satellite_campus = $satellite_campus;
            $param_church = $church;

            if ($stmt->execute()) {
                // Close connection before redirect
                $stmt->close();
                $conn->close();

                // Redirect to login page
                header("Location: login.php?registered=true");
                exit;
            } else {
                echo "Oops! Something went wrong. Please try again later.";
                if (function_exists('logDatabaseError')) {
                    logDatabaseError($sql, $conn->error);
                }
            }

            $stmt->close();
        }
    }
}
?>
<?php
$body_classes = "d-flex justify-content-center align-items-center min-vh-100";
?>
<?php include 'includes/header.php'; ?>

<body>
    <div class="register-container page-container">
        <div class="card cta-block">
            <div class="card-header">
                <img src="logo.png" alt="Virtual Praise Room Logo" style="height: 50px; margin-bottom: 15px;">
                <h3>Create Your Account</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($csrf_err)): ?>
                    <div class="alert alert-danger"><?php echo $csrf_err; ?></div>
                <?php endif; ?>
                <ul class="nav nav-tabs nav-tabs-dark" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active_tab === 'individual' ? 'active' : ''; ?>" id="individual-tab" data-bs-toggle="tab" data-bs-target="#individual" type="button" role="tab" aria-controls="individual" aria-selected="<?php echo $active_tab === 'individual' ? 'true' : 'false'; ?>">Individual</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active_tab === 'choir' ? 'active' : ''; ?>" id="choir-tab" data-bs-toggle="tab" data-bs-target="#choir" type="button" role="tab" aria-controls="choir" aria-selected="<?php echo $active_tab === 'choir' ? 'true' : 'false'; ?>">Choir</button>
                    </li>
                </ul>
                <div class="tab-content" id="myTabContent">
                    <!-- Individual Tab -->
                    <div class="tab-pane fade <?php echo $active_tab === 'individual' ? 'show active' : ''; ?>" id="individual" role="tabpanel" aria-labelledby="individual-tab">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="mt-3">
                            <input type="hidden" name="account_type" value="individual">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                            <div class="mb-3">
                                <input type="text" name="username" class="form-control form-control-dark <?php echo (!empty($username_err) && $active_tab === 'individual') ? 'is-invalid' : ''; ?>" value="<?php echo $active_tab === 'individual' ? htmlspecialchars($username) : ''; ?>" placeholder="Username">
                                <?php if ($active_tab === 'individual' && !empty($username_err)): ?>
                                    <div class="invalid-feedback"><?php echo $username_err; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <input type="text" name="kc_handle" class="form-control form-control-dark" value="<?php echo $active_tab === 'individual' ? htmlspecialchars($kc_handle) : ''; ?>" placeholder="Kc Handle (Optional)">
                            </div>
                            <div class="mb-3">
                                <input type="email" name="email" class="form-control form-control-dark <?php echo (!empty($email_err) && $active_tab === 'individual') ? 'is-invalid' : ''; ?>" value="<?php echo $active_tab === 'individual' ? htmlspecialchars($email) : ''; ?>" placeholder="Email">
                                <?php if ($active_tab === 'individual' && !empty($email_err)): ?>
                                    <div class="invalid-feedback"><?php echo $email_err; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3 position-relative">
                                <input type="text" name="country" id="country_individual" data-dropdown="country-dropdown-individual" class="form-control form-control-dark" value="<?php echo $active_tab === 'individual' ? htmlspecialchars($country) : ''; ?>" placeholder="Country (Optional)" autocomplete="off">
                                <div id="country-dropdown-individual" class="absolute z-10 w-full bg-gray-800 border border-gray-700 rounded-lg mt-1 max-h-60 overflow-y-auto hidden">
                                    <div class="select-option">Afghanistan</div>
                                    <div class="select-option">Albania</div>
                                    <div class="select-option">Algeria</div>
                                    <div class="select-option">Andorra</div>
                                    <div class="select-option">Angola</div>
                                    <div class="select-option">Antigua and Barbuda</div>
                                    <div class="select-option">Argentina</div>
                                    <div class="select-option">Armenia</div>
                                    <div class="select-option">Australia</div>
                                    <div class="select-option">Austria</div>
                                    <div class="select-option">Azerbaijan</div>
                                    <div class="select-option">Bahamas</div>
                                    <div class="select-option">Bahrain</div>
                                    <div class="select-option">Bangladesh</div>
                                    <div class="select-option">Barbados</div>
                                    <div class="select-option">Belarus</div>
                                    <div class="select-option">Belgium</div>
                                    <div class="select-option">Belize</div>
                                    <div class="select-option">Benin</div>
                                    <div class="select-option">Bhutan</div>
                                    <div class="select-option">Bolivia</div>
                                    <div class="select-option">Bosnia and Herzegovina</div>
                                    <div class="select-option">Botswana</div>
                                    <div class="select-option">Brazil</div>
                                    <div class="select-option">Brunei Darussalam</div>
                                    <div class="select-option">Bulgaria</div>
                                    <div class="select-option">Burkina Faso</div>
                                    <div class="select-option">Burundi</div>
                                    <div class="select-option">Cabo Verde</div>
                                    <div class="select-option">Cambodia</div>
                                    <div class="select-option">Cameroon</div>
                                    <div class="select-option">Canada</div>
                                    <div class="select-option">Central African Republic</div>
                                    <div class="select-option">Chad</div>
                                    <div class="select-option">Chile</div>
                                    <div class="select-option">China</div>
                                    <div class="select-option">Colombia</div>
                                    <div class="select-option">Comoros</div>
                                    <div class="select-option">Congo</div>
                                    <div class="select-option">Costa Rica</div>
                                    <div class="select-option">Côte d'Ivoire</div>
                                    <div class="select-option">Croatia</div>
                                    <div class="select-option">Cuba</div>
                                    <div class="select-option">Cyprus</div>
                                    <div class="select-option">Czechia</div>
                                    <div class="select-option">Democratic Republic of the Congo</div>
                                    <div class="select-option">Denmark</div>
                                    <div class="select-option">Djibouti</div>
                                    <div class="select-option">Dominica</div>
                                    <div class="select-option">Dominican Republic</div>
                                    <div class="select-option">Ecuador</div>
                                    <div class="select-option">Egypt</div>
                                    <div class="select-option">El Salvador</div>
                                    <div class="select-option">Equatorial Guinea</div>
                                    <div class="select-option">Eritrea</div>
                                    <div class="select-option">Estonia</div>
                                    <div class="select-option">Eswatini</div>
                                    <div class="select-option">Ethiopia</div>
                                    <div class="select-option">Fiji</div>
                                    <div class="select-option">Finland</div>
                                    <div class="select-option">France</div>
                                    <div class="select-option">Gabon</div>
                                    <div class="select-option">Gambia</div>
                                    <div class="select-option">Georgia</div>
                                    <div class="select-option">Germany</div>
                                    <div class="select-option">Ghana</div>
                                    <div class="select-option">Greece</div>
                                    <div class="select-option">Grenada</div>
                                    <div class="select-option">Guatemala</div>
                                    <div class="select-option">Guinea</div>
                                    <div class="select-option">Guinea-Bissau</div>
                                    <div class="select-option">Guyana</div>
                                    <div class="select-option">Haiti</div>
                                    <div class="select-option">Honduras</div>
                                    <div class="select-option">Hungary</div>
                                    <div class="select-option">Iceland</div>
                                    <div class="select-option">India</div>
                                    <div class="select-option">Indonesia</div>
                                    <div class="select-option">Iran</div>
                                    <div class="select-option">Iraq</div>
                                    <div class="select-option">Ireland</div>
                                    <div class="select-option">Israel</div>
                                    <div class="select-option">Italy</div>
                                    <div class="select-option">Jamaica</div>
                                    <div class="select-option">Japan</div>
                                    <div class="select-option">Jordan</div>
                                    <div class="select-option">Kazakhstan</div>
                                    <div class="select-option">Kenya</div>
                                    <div class="select-option">Kiribati</div>
                                    <div class="select-option">Kuwait</div>
                                    <div class="select-option">Kyrgyzstan</div>
                                    <div class="select-option">Lao People's Democratic Republic</div>
                                    <div class="select-option">Latvia</div>
                                    <div class="select-option">Lebanon</div>
                                    <div class="select-option">Lesotho</div>
                                    <div class="select-option">Liberia</div>
                                    <div class="select-option">Libya</div>
                                    <div class="select-option">Liechtenstein</div>
                                    <div class="select-option">Lithuania</div>
                                    <div class="select-option">Luxembourg</div>
                                    <div class="select-option">Madagascar</div>
                                    <div class="select-option">Malawi</div>
                                    <div class="select-option">Malaysia</div>
                                    <div class="select-option">Maldives</div>
                                    <div class="select-option">Mali</div>
                                    <div class="select-option">Malta</div>
                                    <div class="select-option">Marshall Islands</div>
                                    <div class="select-option">Mauritania</div>
                                    <div class="select-option">Mauritius</div>
                                    <div class="select-option">Mexico</div>
                                    <div class="select-option">Micronesia</div>
                                    <div class="select-option">Moldova</div>
                                    <div class="select-option">Monaco</div>
                                    <div class="select-option">Mongolia</div>
                                    <div class="select-option">Montenegro</div>
                                    <div class="select-option">Morocco</div>
                                    <div class="select-option">Mozambique</div>
                                    <div class="select-option">Myanmar</div>
                                    <div class="select-option">Namibia</div>
                                    <div class="select-option">Nauru</div>
                                    <div class="select-option">Nepal</div>
                                    <div class="select-option">Netherlands</div>
                                    <div class="select-option">New Zealand</div>
                                    <div class="select-option">Nicaragua</div>
                                    <div class="select-option">Niger</div>
                                    <div class="select-option">Nigeria</div>
                                    <div class="select-option">North Korea</div>
                                    <div class="select-option">North Macedonia</div>
                                    <div class="select-option">Norway</div>
                                    <div class="select-option">Oman</div>
                                    <div class="select-option">Pakistan</div>
                                    <div class="select-option">Palau</div>
                                    <div class="select-option">Panama</div>
                                    <div class="select-option">Papua New Guinea</div>
                                    <div class="select-option">Paraguay</div>
                                    <div class="select-option">Peru</div>
                                    <div class="select-option">Philippines</div>
                                    <div class="select-option">Poland</div>
                                    <div class="select-option">Portugal</div>
                                    <div class="select-option">Qatar</div>
                                    <div class="select-option">Republic of Korea</div>
                                    <div class="select-option">Republic of Moldova</div>
                                    <div class="select-option">Romania</div>
                                    <div class="select-option">Russian Federation</div>
                                    <div class="select-option">Rwanda</div>
                                    <div class="select-option">Saint Kitts and Nevis</div>
                                    <div class="select-option">Saint Lucia</div>
                                    <div class="select-option">Saint Vincent and the Grenadines</div>
                                    <div class="select-option">Samoa</div>
                                    <div class="select-option">San Marino</div>
                                    <div class="select-option">Sao Tome and Principe</div>
                                    <div class="select-option">Saudi Arabia</div>
                                    <div class="select-option">Senegal</div>
                                    <div class="select-option">Serbia</div>
                                    <div class="select-option">Seychelles</div>
                                    <div class="select-option">Sierra Leone</div>
                                    <div class="select-option">Singapore</div>
                                    <div class="select-option">Slovakia</div>
                                    <div class="select-option">Slovenia</div>
                                    <div class="select-option">Solomon Islands</div>
                                    <div class="select-option">Somalia</div>
                                    <div class="select-option">South Africa</div>
                                    <div class="select-option">South Sudan</div>
                                    <div class="select-option">Spain</div>
                                    <div class="select-option">Sri Lanka</div>
                                    <div class="select-option">Sudan</div>
                                    <div class="select-option">Suriname</div>
                                    <div class="select-option">Sweden</div>
                                    <div class="select-option">Switzerland</div>
                                    <div class="select-option">Syrian Arab Republic</div>
                                    <div class="select-option">Tajikistan</div>
                                    <div class="select-option">Tanzania</div>
                                    <div class="select-option">Thailand</div>
                                    <div class="select-option">Timor-Leste</div>
                                    <div class="select-option">Togo</div>
                                    <div class="select-option">Tonga</div>
                                    <div class="select-option">Trinidad and Tobago</div>
                                    <div class="select-option">Tunisia</div>
                                    <div class="select-option">Turkey</div>
                                    <div class="select-option">Turkmenistan</div>
                                    <div class="select-option">Tuvalu</div>
                                    <div class="select-option">Uganda</div>
                                    <div class="select-option">Ukraine</div>
                                    <div class="select-option">United Arab Emirates</div>
                                    <div class="select-option">United Kingdom</div>
                                    <div class="select-option">United States of America</div>
                                    <div class="select-option">Uruguay</div>
                                    <div class="select-option">Uzbekistan</div>
                                    <div class="select-option">Vanuatu</div>
                                    <div class="select-option">Venezuela</div>
                                    <div class="select-option">Viet Nam</div>
                                    <div class="select-option">Yemen</div>
                                    <div class="select-option">Zambia</div>
                                    <div class="select-option">Zimbabwe</div>
                                </div>
                            </div>
                            <div class="mb-3 position-relative">
                                <input type="text" name="satellite_campus" id="satellite_campus_individual" data-dropdown="zone-dropdown-individual" class="form-control form-control-dark <?php echo (!empty($satellite_campus_err) && $active_tab === 'individual') ? 'is-invalid' : ''; ?>" value="<?php echo $active_tab === 'individual' ? htmlspecialchars($satellite_campus) : ''; ?>" placeholder="Satellite Campus (Zones)" autocomplete="off" required>
                                <?php if ($active_tab === 'individual' && !empty($satellite_campus_err)): ?>
                                    <div class="invalid-feedback"><?php echo $satellite_campus_err; ?></div>
                                <?php endif; ?>
                                <div id="zone-dropdown-individual" class="absolute z-10 w-full bg-gray-800 border border-gray-700 rounded-lg mt-1 max-h-60 overflow-y-auto hidden">
                                    <div class="select-option" onclick="selectZone('LoveWorld church Zone')">LoveWorld church Zone</div>
                                    <div class="select-option" onclick="selectZone('ABA ZONE')">ABA ZONE</div>
                                    <div class="select-option" onclick="selectZone('ABUJA ZONE 1')">ABUJA ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('ABUJA ZONE 2')">ABUJA ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('ACCRA GHANA ZONE')">ACCRA GHANA ZONE</div>
                                    <div class="select-option" onclick="selectZone('AMSTERDAM DSP')">AMSTERDAM DSP</div>
                                    <div class="select-option" onclick="selectZone('ATLANTA GROUP')">ATLANTA GROUP</div>
                                    <div class="select-option" onclick="selectZone('AUSTRALIA REGION')">AUSTRALIA REGION</div>
                                    <div class="select-option" onclick="selectZone('BENIN ZONE 1')">BENIN ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('BENIN ZONE 2')">BENIN ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('CAPE TOWN ZONE 1')">CAPE TOWN ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('CAPE TOWN ZONE 2')">CAPE TOWN ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('CHAD')">CHAD</div>
                                    <div class="select-option" onclick="selectZone('DALLAS ZONE')">DALLAS ZONE</div>
                                    <div class="select-option" onclick="selectZone('DSC SUBZONE')">DSC SUBZONE</div>
                                    <div class="select-option" onclick="selectZone('DURBAN ZONE')">DURBAN ZONE</div>
                                    <div class="select-option" onclick="selectZone('EAST ASIA')">EAST ASIA</div>
                                    <div class="select-option" onclick="selectZone('EASTERN EUROPE REGION')">EASTERN EUROPE REGION</div>
                                    <div class="select-option" onclick="selectZone('EDO NORTH &amp; EDO CENTRAL ZONE')">EDO NORTH &amp; EDO CENTRAL ZONE</div>
                                    <div class="select-option" onclick="selectZone('EWCA ZONE 6')">EWCA ZONE 6</div>
                                    <div class="select-option" onclick="selectZone('EWCA ZONE 1')">EWCA ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('EWCA ZONE 2')">EWCA ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('EWCA ZONE 3')">EWCA ZONE 3</div>
                                    <div class="select-option" onclick="selectZone('EWCA ZONE 4')">EWCA ZONE 4</div>
                                    <div class="select-option" onclick="selectZone('EWCA ZONE 5')">EWCA ZONE 5</div>
                                    <div class="select-option" onclick="selectZone('IBADAN ZONE 1')">IBADAN ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('IBADAN ZONE 2')">IBADAN ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('INDIA ZONE')">INDIA ZONE</div>
                                    <div class="select-option" onclick="selectZone('INTERNATIONAL MISSIONS TO SOUTH EAST ASIA')">INTERNATIONAL MISSIONS TO SOUTH EAST ASIA</div>
                                    <div class="select-option" onclick="selectZone('KENYA ZONE')">KENYA ZONE</div>
                                    <div class="select-option" onclick="selectZone('LAGOS SUB ZONE C')">LAGOS SUB ZONE C</div>
                                    <div class="select-option" onclick="selectZone('LAGOS SUBZONE A')">LAGOS SUBZONE A</div>
                                    <div class="select-option" onclick="selectZone('LAGOS SUBZONE B')">LAGOS SUBZONE B</div>
                                    <div class="select-option" onclick="selectZone('LAGOS VIRTUAL ZONE')">LAGOS VIRTUAL ZONE</div>
                                    <div class="select-option" onclick="selectZone('LAGOS ZONE 1')">LAGOS ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('LAGOS ZONE 2')">LAGOS ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('LAGOS ZONE 3')">LAGOS ZONE 3</div>
                                    <div class="select-option" onclick="selectZone('LAGOS ZONE 4')">LAGOS ZONE 4</div>
                                    <div class="select-option" onclick="selectZone('LAGOS ZONE 5')">LAGOS ZONE 5</div>
                                    <div class="select-option" onclick="selectZone('LAGOS ZONE 6')">LAGOS ZONE 6</div>
                                    <div class="select-option" onclick="selectZone('LOVEWORLD ZONE')">LOVEWORLD ZONE</div>
                                    <div class="select-option" onclick="selectZone('MIDDLE EAST &amp; ASIA REGION')">MIDDLE EAST &amp; ASIA REGION</div>
                                    <div class="select-option" onclick="selectZone('MIDWEST ZONE')">MIDWEST ZONE</div>
                                    <div class="select-option" onclick="selectZone('MINISTRY CENTER ABEOKUTA')">MINISTRY CENTER ABEOKUTA</div>
                                    <div class="select-option" onclick="selectZone('MINISTRY CENTER ABUJA')">MINISTRY CENTER ABUJA</div>
                                    <div class="select-option" onclick="selectZone('MINISTRY CENTER CALABAR')">MINISTRY CENTER CALABAR</div>
                                    <div class="select-option" onclick="selectZone('MINISTRY CENTRE IBADAN')">MINISTRY CENTRE IBADAN</div>
                                    <div class="select-option" onclick="selectZone('MINISTRY CENTRE WARRI')">MINISTRY CENTRE WARRI</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA NORTH CENTRAL ZONE 1')">NIGERIA NORTH CENTRAL ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA NORTH CENTRAL ZONE 2')">NIGERIA NORTH CENTRAL ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA NORTH WEST ZONE 1')">NIGERIA NORTH WEST ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA NORTHERN REGION 1 ZONE 1')">NIGERIA NORTHERN REGION 1 ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA NORTHERN REGION 1 ZONE 2')">NIGERIA NORTHERN REGION 1 ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA SOUTH EAST ZONE 1')">NIGERIA SOUTH EAST ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA SOUTH EAST ZONE 3')">NIGERIA SOUTH EAST ZONE 3</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA SOUTH WEST ZONE 2')">NIGERIA SOUTH WEST ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA SOUTH WEST ZONE 3')">NIGERIA SOUTH WEST ZONE 3</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA SOUTH WEST ZONE 4')">NIGERIA SOUTH WEST ZONE 4</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA SOUTH WEST ZONE 5')">NIGERIA SOUTH WEST ZONE 5</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA SOUTHSOUTH ZONE 3')">NIGERIA SOUTHSOUTH ZONE 3</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA SOUTHSOUTH ZONE 1')">NIGERIA SOUTHSOUTH ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA SOUTHSOUTH ZONE 2')">NIGERIA SOUTHSOUTH ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('ONITSHA')">ONITSHA</div>
                                    <div class="select-option" onclick="selectZone('OTTAWA ZONE')">OTTAWA ZONE</div>
                                    <div class="select-option" onclick="selectZone('PORT HARCOURT ZONE 1')">PORT HARCOURT ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('PORT HARCOURT ZONE 2')">PORT HARCOURT ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('PORTHARCOURT ZONE 3')">PORTHARCOURT ZONE 3</div>
                                    <div class="select-option" onclick="selectZone('QUEBEC ZONE')">QUEBEC ZONE</div>
                                    <div class="select-option" onclick="selectZone('SOUTH AFRICA ZONE 1')">SOUTH AFRICA ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('SOUTH AFRICA ZONE 2')">SOUTH AFRICA ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('SOUTH AFRICA ZONE 3')">SOUTH AFRICA ZONE 3</div>
                                    <div class="select-option" onclick="selectZone('SOUTH AFRICA ZONE 5')">SOUTH AFRICA ZONE 5</div>
                                    <div class="select-option" onclick="selectZone('SOUTH AMERICA REGION')">SOUTH AMERICA REGION</div>
                                    <div class="select-option" onclick="selectZone('TORONTO ZONE')">TORONTO ZONE</div>
                                    <div class="select-option" onclick="selectZone('UK REGION 1 ZONE 1')">UK REGION 1 ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('UK REGION 1 ZONE 2')">UK REGION 1 ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('UK REGION 1 ZONE 3')">UK REGION 1 ZONE 3</div>
                                    <div class="select-option" onclick="selectZone('UK REGION 1 ZONE 4')">UK REGION 1 ZONE 4</div>
                                    <div class="select-option" onclick="selectZone('UK REGION 2 ZONE 1')">UK REGION 2 ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('UK REGION 2 ZONE 3')">UK REGION 2 ZONE 3</div>
                                    <div class="select-option" onclick="selectZone('UK REGION 2 ZONE 4')">UK REGION 2 ZONE 4</div>
                                    <div class="select-option" onclick="selectZone('USA REGION 1 ZONE 1')">USA REGION 1 ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('USA REGION 1 ZONE 2')">USA REGION 1 ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('USA REGION 2')">USA REGION 2</div>
                                    <div class="select-option" onclick="selectZone('USA REGION 3')">USA REGION 3</div>
                                    <div class="select-option" onclick="selectZone('WESTERN EUROPE ZONE 1')">WESTERN EUROPE ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('WESTERN EUROPE ZONE 2')">WESTERN EUROPE ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('WESTERN EUROPE ZONE 3')">WESTERN EUROPE ZONE 3</div>
                                    <div class="select-option" onclick="selectZone('WESTERN EUROPE ZONE 4')">WESTERN EUROPE ZONE 4</div>
                                    <div class="select-option" onclick="selectZone('BLW Benin Republic Zone')">BLW Benin Republic Zone</div>
                                    <div class="select-option" onclick="selectZone('BLW Burkina Faso')">BLW Burkina Faso</div>
                                    <div class="select-option" onclick="selectZone('BLW Cameroon Zone')">BLW Cameroon Zone</div>
                                    <div class="select-option" onclick="selectZone('BLW Canada Region')">BLW Canada Region</div>
                                    <div class="select-option" onclick="selectZone('BLW DR Congo')">BLW DR Congo</div>
                                    <div class="select-option" onclick="selectZone('BLW Ghana Zone A')">BLW Ghana Zone A</div>
                                    <div class="select-option" onclick="selectZone('BLW Ghana Zone B')">BLW Ghana Zone B</div>
                                    <div class="select-option" onclick="selectZone('BLW Ghana Zone C')">BLW Ghana Zone C</div>
                                    <div class="select-option" onclick="selectZone('BLW Ghana Zone D')">BLW Ghana Zone D</div>
                                    <div class="select-option" onclick="selectZone('BLW Ireland Zone')">BLW Ireland Zone</div>
                                    <div class="select-option" onclick="selectZone('BLW Kenya Zone')">BLW Kenya Zone</div>
                                    <div class="select-option" onclick="selectZone('BLW Middle East and North Africa Region')">BLW Middle East and North Africa Region</div>
                                    <div class="select-option" onclick="selectZone('BLW SA Zone A')">BLW SA Zone A</div>
                                    <div class="select-option" onclick="selectZone('BLW SA Zone B')">BLW SA Zone B</div>
                                    <div class="select-option" onclick="selectZone('BLW SA Zone C')">BLW SA Zone C</div>
                                    <div class="select-option" onclick="selectZone('BLW SA Zone D')">BLW SA Zone D</div>
                                    <div class="select-option" onclick="selectZone('BLW SA Zone E')">BLW SA Zone E</div>
                                    <div class="select-option" onclick="selectZone('BLW SA Zone F')">BLW SA Zone F</div>
                                    <div class="select-option" onclick="selectZone('BLW SA Zone G')">BLW SA Zone G</div>
                                    <div class="select-option" onclick="selectZone('BLW Tanzania')">BLW Tanzania</div>
                                    <div class="select-option" onclick="selectZone('BLW Uganda Zone A')">BLW Uganda Zone A</div>
                                    <div class="select-option" onclick="selectZone('BLW Uganda Zone B')">BLW Uganda Zone B</div>
                                    <div class="select-option" onclick="selectZone('BLW UK Zone A')">BLW UK Zone A</div>
                                    <div class="select-option" onclick="selectZone('BLW UK Zone B')">BLW UK Zone B</div>
                                    <div class="select-option" onclick="selectZone('BLW UK Zone C')">BLW UK Zone C</div>
                                    <div class="select-option" onclick="selectZone('BLW USA Region 1')">BLW USA Region 1</div>
                                    <div class="select-option" onclick="selectZone('BLW USA Region 2')">BLW USA Region 2</div>
                                    <div class="select-option" onclick="selectZone('BLW Wales Zone')">BLW Wales Zone</div>
                                    <div class="select-option" onclick="selectZone('BLW West Africa Region')">BLW West Africa Region</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone A')">BLW Zone A</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone B')">BLW Zone B</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone C')">BLW Zone C</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone D')">BLW Zone D</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone E')">BLW Zone E</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone F')">BLW Zone F</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone G')">BLW Zone G</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone H')">BLW Zone H</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone I')">BLW Zone I</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone J')">BLW Zone J</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone K')">BLW Zone K</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone L')">BLW Zone L</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone M')">BLW Zone M</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone N')">BLW Zone N</div>
                                    <div class="select-option" onclick="selectZone('Loveworld Singes HQ')">Loveworld Singes HQ</div>
                                    <div class="select-option" onclick="selectZone('PMC HQ')">PMC HQ</div>
                                    <div class="select-option" onclick="selectZone('24 Worship Band HQ')">24 Worship Band HQ</div>
                                    <div class="select-option" onclick="selectZone('Orchestra HQ')">Orchestra HQ</div>
                                    <div class="select-option" onclick="selectZone('LGF')">LGF</div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="input-group">
                                    <input type="password" name="password" id="password_individual" class="form-control form-control-dark <?php echo (!empty($password_err) && $active_tab === 'individual') ? 'is-invalid' : ''; ?>" placeholder="Password">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePasswordIndividual">
                                        <i class="fa fa-eye-slash" aria-hidden="true"></i>
                                    </button>
                                    <?php if ($active_tab === 'individual' && !empty($password_err)): ?>
                                        <div class="invalid-feedback"><?php echo $password_err; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="input-group">
                                    <input type="password" name="confirm_password" id="confirm_password_individual" class="form-control form-control-dark <?php echo (!empty($confirm_password_err) && $active_tab === 'individual') ? 'is-invalid' : ''; ?>" placeholder="Confirm Password">
                                    <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPasswordIndividual">
                                        <i class="fa fa-eye-slash" aria-hidden="true"></i>
                                    </button>
                                    <?php if ($active_tab === 'individual' && !empty($confirm_password_err)): ?>
                                        <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary-custom">Register</button>
                            </div>
                        </form>
                    </div>

                    <!-- Choir Tab -->
                    <div class="tab-pane fade <?php echo $active_tab === 'choir' ? 'show active' : ''; ?>" id="choir" role="tabpanel" aria-labelledby="choir-tab">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="mt-3">
                            <input type="hidden" name="account_type" value="choir">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                            <div class="mb-3">
                                <input type="text" name="username" class="form-control form-control-dark <?php echo (!empty($username_err) && $active_tab === 'choir') ? 'is-invalid' : ''; ?>" value="<?php echo $active_tab === 'choir' ? htmlspecialchars($username) : ''; ?>" placeholder="Choir Name">
                                <?php if ($active_tab === 'choir' && !empty($username_err)): ?>
                                    <div class="invalid-feedback"><?php echo $username_err; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <input type="text" name="kc_handle" class="form-control form-control-dark" value="<?php echo $active_tab === 'choir' ? htmlspecialchars($kc_handle) : ''; ?>" placeholder="Kc Handle (Optional)">
                            </div>
                            <div class="mb-3">
                                <input type="email" name="email" class="form-control form-control-dark <?php echo (!empty($email_err) && $active_tab === 'choir') ? 'is-invalid' : ''; ?>" value="<?php echo $active_tab === 'choir' ? htmlspecialchars($email) : ''; ?>" placeholder="Email">
                                <?php if ($active_tab === 'choir' && !empty($email_err)): ?>
                                    <div class="invalid-feedback"><?php echo $email_err; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <div class="input-group">
                                    <input type="password" name="password" id="password_choir" class="form-control form-control-dark <?php echo (!empty($password_err) && $active_tab === 'choir') ? 'is-invalid' : ''; ?>" placeholder="Password">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePasswordChoir">
                                        <i class="fa fa-eye-slash" aria-hidden="true"></i>
                                    </button>
                                    <?php if ($active_tab === 'choir' && !empty($password_err)): ?>
                                        <div class="invalid-feedback"><?php echo $password_err; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="input-group">
                                    <input type="password" name="confirm_password" id="confirm_password_choir" class="form-control form-control-dark <?php echo (!empty($confirm_password_err) && $active_tab === 'choir') ? 'is-invalid' : ''; ?>" placeholder="Confirm Password">
                                    <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPasswordChoir">
                                        <i class="fa fa-eye-slash" aria-hidden="true"></i>
                                    </button>
                                    <?php if ($active_tab === 'choir' && !empty($confirm_password_err)): ?>
                                        <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mb-3 position-relative">
                                <input type="text" name="country" id="country_choir" data-dropdown="country-dropdown-choir" class="form-control form-control-dark" value="<?php echo $active_tab === 'choir' ? htmlspecialchars($country) : ''; ?>" placeholder="Country" autocomplete="off">
                                <div id="country-dropdown-choir" class="absolute z-10 w-full bg-gray-800 border border-gray-700 rounded-lg mt-1 max-h-60 overflow-y-auto hidden">
                                    <div class="select-option">Afghanistan</div>
                                    <div class="select-option">Albania</div>
                                    <div class="select-option">Algeria</div>
                                    <div class="select-option">Andorra</div>
                                    <div class="select-option">Angola</div>
                                    <div class="select-option">Antigua and Barbuda</div>
                                    <div class="select-option">Argentina</div>
                                    <div class="select-option">Armenia</div>
                                    <div class="select-option">Australia</div>
                                    <div class="select-option">Austria</div>
                                    <div class="select-option">Azerbaijan</div>
                                    <div class="select-option">Bahamas</div>
                                    <div class="select-option">Bahrain</div>
                                    <div class="select-option">Bangladesh</div>
                                    <div class="select-option">Barbados</div>
                                    <div class="select-option">Belarus</div>
                                    <div class="select-option">Belgium</div>
                                    <div class="select-option">Belize</div>
                                    <div class="select-option">Benin</div>
                                    <div class="select-option">Bhutan</div>
                                    <div class="select-option">Bolivia</div>
                                    <div class="select-option">Bosnia and Herzegovina</div>
                                    <div class="select-option">Botswana</div>
                                    <div class="select-option">Brazil</div>
                                    <div class="select-option">Brunei Darussalam</div>
                                    <div class="select-option">Bulgaria</div>
                                    <div class="select-option">Burkina Faso</div>
                                    <div class="select-option">Burundi</div>
                                    <div class="select-option">Cabo Verde</div>
                                    <div class="select-option">Cambodia</div>
                                    <div class="select-option">Cameroon</div>
                                    <div class="select-option">Canada</div>
                                    <div class="select-option">Central African Republic</div>
                                    <div class="select-option">Chad</div>
                                    <div class="select-option">Chile</div>
                                    <div class="select-option">China</div>
                                    <div class="select-option">Colombia</div>
                                    <div class="select-option">Comoros</div>
                                    <div class="select-option">Congo</div>
                                    <div class="select-option">Costa Rica</div>
                                    <div class="select-option">Côte d'Ivoire</div>
                                    <div class="select-option">Croatia</div>
                                    <div class="select-option">Cuba</div>
                                    <div class="select-option">Cyprus</div>
                                    <div class="select-option">Czechia</div>
                                    <div class="select-option">Democratic Republic of the Congo</div>
                                    <div class="select-option">Denmark</div>
                                    <div class="select-option">Djibouti</div>
                                    <div class="select-option">Dominica</div>
                                    <div class="select-option">Dominican Republic</div>
                                    <div class="select-option">Ecuador</div>
                                    <div class="select-option">Egypt</div>
                                    <div class="select-option">El Salvador</div>
                                    <div class="select-option">Equatorial Guinea</div>
                                    <div class="select-option">Eritrea</div>
                                    <div class="select-option">Estonia</div>
                                    <div class="select-option">Eswatini</div>
                                    <div class="select-option">Ethiopia</div>
                                    <div class="select-option">Fiji</div>
                                    <div class="select-option">Finland</div>
                                    <div class="select-option">France</div>
                                    <div class="select-option">Gabon</div>
                                    <div class="select-option">Gambia</div>
                                    <div class="select-option">Georgia</div>
                                    <div class="select-option">Germany</div>
                                    <div class="select-option">Ghana</div>
                                    <div class="select-option">Greece</div>
                                    <div class="select-option">Grenada</div>
                                    <div class="select-option">Guatemala</div>
                                    <div class="select-option">Guinea</div>
                                    <div class="select-option">Guinea-Bissau</div>
                                    <div class="select-option">Guyana</div>
                                    <div class="select-option">Haiti</div>
                                    <div class="select-option">Honduras</div>
                                    <div class="select-option">Hungary</div>
                                    <div class="select-option">Iceland</div>
                                    <div class="select-option">India</div>
                                    <div class="select-option">Indonesia</div>
                                    <div class="select-option">Iran</div>
                                    <div class="select-option">Iraq</div>
                                    <div class="select-option">Ireland</div>
                                    <div class="select-option">Israel</div>
                                    <div class="select-option">Italy</div>
                                    <div class="select-option">Jamaica</div>
                                    <div class="select-option">Japan</div>
                                    <div class="select-option">Jordan</div>
                                    <div class="select-option">Kazakhstan</div>
                                    <div class="select-option">Kenya</div>
                                    <div class="select-option">Kiribati</div>
                                    <div class="select-option">Kuwait</div>
                                    <div class="select-option">Kyrgyzstan</div>
                                    <div class="select-option">Lao People's Democratic Republic</div>
                                    <div class="select-option">Latvia</div>
                                    <div class="select-option">Lebanon</div>
                                    <div class="select-option">Lesotho</div>
                                    <div class="select-option">Liberia</div>
                                    <div class="select-option">Libya</div>
                                    <div class="select-option">Liechtenstein</div>
                                    <div class="select-option">Lithuania</div>
                                    <div class="select-option">Luxembourg</div>
                                    <div class="select-option">Madagascar</div>
                                    <div class="select-option">Malawi</div>
                                    <div class="select-option">Malaysia</div>
                                    <div class="select-option">Maldives</div>
                                    <div class="select-option">Mali</div>
                                    <div class="select-option">Malta</div>
                                    <div class="select-option">Marshall Islands</div>
                                    <div class="select-option">Mauritania</div>
                                    <div class="select-option">Mauritius</div>
                                    <div class="select-option">Mexico</div>
                                    <div class="select-option">Micronesia</div>
                                    <div class="select-option">Moldova</div>
                                    <div class="select-option">Monaco</div>
                                    <div class="select-option">Mongolia</div>
                                    <div class="select-option">Montenegro</div>
                                    <div class="select-option">Morocco</div>
                                    <div class="select-option">Mozambique</div>
                                    <div class="select-option">Myanmar</div>
                                    <div class="select-option">Namibia</div>
                                    <div class="select-option">Nauru</div>
                                    <div class="select-option">Nepal</div>
                                    <div class="select-option">Netherlands</div>
                                    <div class="select-option">New Zealand</div>
                                    <div class="select-option">Nicaragua</div>
                                    <div class="select-option">Niger</div>
                                    <div class="select-option">Nigeria</div>
                                    <div class="select-option">North Korea</div>
                                    <div class="select-option">North Macedonia</div>
                                    <div class="select-option">Norway</div>
                                    <div class="select-option">Oman</div>
                                    <div class="select-option">Pakistan</div>
                                    <div class="select-option">Palau</div>
                                    <div class="select-option">Panama</div>
                                    <div class="select-option">Papua New Guinea</div>
                                    <div class="select-option">Paraguay</div>
                                    <div class="select-option">Peru</div>
                                    <div class="select-option">Philippines</div>
                                    <div class="select-option">Poland</div>
                                    <div class="select-option">Portugal</div>
                                    <div class="select-option">Qatar</div>
                                    <div class="select-option">Republic of Korea</div>
                                    <div class="select-option">Republic of Moldova</div>
                                    <div class="select-option">Romania</div>
                                    <div class="select-option">Russian Federation</div>
                                    <div class="select-option">Rwanda</div>
                                    <div class="select-option">Saint Kitts and Nevis</div>
                                    <div class="select-option">Saint Lucia</div>
                                    <div class="select-option">Saint Vincent and the Grenadines</div>
                                    <div class="select-option">Samoa</div>
                                    <div class="select-option">San Marino</div>
                                    <div class="select-option">Sao Tome and Principe</div>
                                    <div class="select-option">Saudi Arabia</div>
                                    <div class="select-option">Senegal</div>
                                    <div class="select-option">Serbia</div>
                                    <div class="select-option">Seychelles</div>
                                    <div class="select-option">Sierra Leone</div>
                                    <div class="select-option">Singapore</div>
                                    <div class="select-option">Slovakia</div>
                                    <div class="select-option">Slovenia</div>
                                    <div class="select-option">Solomon Islands</div>
                                    <div class="select-option">Somalia</div>
                                    <div class="select-option">South Africa</div>
                                    <div class="select-option">South Sudan</div>
                                    <div class="select-option">Spain</div>
                                    <div class="select-option">Sri Lanka</div>
                                    <div class="select-option">Sudan</div>
                                    <div class="select-option">Suriname</div>
                                    <div class="select-option">Sweden</div>
                                    <div class="select-option">Switzerland</div>
                                    <div class="select-option">Syrian Arab Republic</div>
                                    <div class="select-option">Tajikistan</div>
                                    <div class="select-option">Tanzania</div>
                                    <div class="select-option">Thailand</div>
                                    <div class="select-option">Timor-Leste</div>
                                    <div class="select-option">Togo</div>
                                    <div class="select-option">Tonga</div>
                                    <div class="select-option">Trinidad and Tobago</div>
                                    <div class="select-option">Tunisia</div>
                                    <div class="select-option">Turkey</div>
                                    <div class="select-option">Turkmenistan</div>
                                    <div class="select-option">Tuvalu</div>
                                    <div class="select-option">Uganda</div>
                                    <div class="select-option">Ukraine</div>
                                    <div class="select-option">United Arab Emirates</div>
                                    <div class="select-option">United Kingdom</div>
                                    <div class="select-option">United States of America</div>
                                    <div class="select-option">Uruguay</div>
                                    <div class="select-option">Uzbekistan</div>
                                    <div class="select-option">Vanuatu</div>
                                    <div class="select-option">Venezuela</div>
                                    <div class="select-option">Viet Nam</div>
                                    <div class="select-option">Yemen</div>
                                    <div class="select-option">Zambia</div>
                                    <div class="select-option">Zimbabwe</div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <input type="text" name="city" class="form-control form-control-dark" value="<?php echo $active_tab === 'choir' ? htmlspecialchars($city) : ''; ?>" placeholder="City">
                            </div>
                            <div class="mb-3">
                                <input type="text" name="region" class="form-control form-control-dark" value="<?php echo $active_tab === 'choir' ? htmlspecialchars($region) : ''; ?>" placeholder="Region">
                            </div>
                            <div class="mb-3 position-relative">
                                <input type="text" name="satellite_campus" id="satellite_campus_choir" data-dropdown="zone-dropdown-choir" class="form-control form-control-dark <?php echo (!empty($satellite_campus_err) && $active_tab === 'choir') ? 'is-invalid' : ''; ?>" value="<?php echo $active_tab === 'choir' ? htmlspecialchars($satellite_campus) : ''; ?>" placeholder="Satellite Campus" autocomplete="off" required>
                                <?php if ($active_tab === 'choir' && !empty($satellite_campus_err)): ?>
                                    <div class="invalid-feedback"><?php echo $satellite_campus_err; ?></div>
                                <?php endif; ?>
                                <div id="zone-dropdown-choir" class="absolute z-10 w-full bg-gray-800 border border-gray-700 rounded-lg mt-1 max-h-60 overflow-y-auto hidden">
                                    <div class="select-option" onclick="selectZone('LoveWorld church Zone')">LoveWorld church Zone</div>
                                    <div class="select-option" onclick="selectZone('ABA ZONE')">ABA ZONE</div>
                                    <div class="select-option" onclick="selectZone('ABUJA ZONE 1')">ABUJA ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('ABUJA ZONE 2')">ABUJA ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('ACCRA GHANA ZONE')">ACCRA GHANA ZONE</div>
                                    <div class="select-option" onclick="selectZone('AMSTERDAM DSP')">AMSTERDAM DSP</div>
                                    <div class="select-option" onclick="selectZone('ATLANTA GROUP')">ATLANTA GROUP</div>
                                    <div class="select-option" onclick="selectZone('AUSTRALIA REGION')">AUSTRALIA REGION</div>
                                    <div class="select-option" onclick="selectZone('BENIN ZONE 1')">BENIN ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('BENIN ZONE 2')">BENIN ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('CAPE TOWN ZONE 1')">CAPE TOWN ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('CAPE TOWN ZONE 2')">CAPE TOWN ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('CHAD')">CHAD</div>
                                    <div class="select-option" onclick="selectZone('DALLAS ZONE')">DALLAS ZONE</div>
                                    <div class="select-option" onclick="selectZone('DSC SUBZONE')">DSC SUBZONE</div>
                                    <div class="select-option" onclick="selectZone('DURBAN ZONE')">DURBAN ZONE</div>
                                    <div class="select-option" onclick="selectZone('EAST ASIA')">EAST ASIA</div>
                                    <div class="select-option" onclick="selectZone('EASTERN EUROPE REGION')">EASTERN EUROPE REGION</div>
                                    <div class="select-option" onclick="selectZone('EDO NORTH &amp; EDO CENTRAL ZONE')">EDO NORTH &amp; EDO CENTRAL ZONE</div>
                                    <div class="select-option" onclick="selectZone('EWCA ZONE 6')">EWCA ZONE 6</div>
                                    <div class="select-option" onclick="selectZone('EWCA ZONE 1')">EWCA ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('EWCA ZONE 2')">EWCA ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('EWCA ZONE 3')">EWCA ZONE 3</div>
                                    <div class="select-option" onclick="selectZone('EWCA ZONE 4')">EWCA ZONE 4</div>
                                    <div class="select-option" onclick="selectZone('EWCA ZONE 5')">EWCA ZONE 5</div>
                                    <div class="select-option" onclick="selectZone('IBADAN ZONE 1')">IBADAN ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('IBADAN ZONE 2')">IBADAN ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('INDIA ZONE')">INDIA ZONE</div>
                                    <div class="select-option" onclick="selectZone('INTERNATIONAL MISSIONS TO SOUTH EAST ASIA')">INTERNATIONAL MISSIONS TO SOUTH EAST ASIA</div>
                                    <div class="select-option" onclick="selectZone('KENYA ZONE')">KENYA ZONE</div>
                                    <div class="select-option" onclick="selectZone('LAGOS SUB ZONE C')">LAGOS SUB ZONE C</div>
                                    <div class="select-option" onclick="selectZone('LAGOS SUBZONE A')">LAGOS SUBZONE A</div>
                                    <div class="select-option" onclick="selectZone('LAGOS SUBZONE B')">LAGOS SUBZONE B</div>
                                    <div class="select-option" onclick="selectZone('LAGOS VIRTUAL ZONE')">LAGOS VIRTUAL ZONE</div>
                                    <div class="select-option" onclick="selectZone('LAGOS ZONE 1')">LAGOS ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('LAGOS ZONE 2')">LAGOS ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('LAGOS ZONE 3')">LAGOS ZONE 3</div>
                                    <div class="select-option" onclick="selectZone('LAGOS ZONE 4')">LAGOS ZONE 4</div>
                                    <div class="select-option" onclick="selectZone('LAGOS ZONE 5')">LAGOS ZONE 5</div>
                                    <div class="select-option" onclick="selectZone('LAGOS ZONE 6')">LAGOS ZONE 6</div>
                                    <div class="select-option" onclick="selectZone('LOVEWORLD ZONE')">LOVEWORLD ZONE</div>
                                    <div class="select-option" onclick="selectZone('MIDDLE EAST &amp; ASIA REGION')">MIDDLE EAST &amp; ASIA REGION</div>
                                    <div class="select-option" onclick="selectZone('MIDWEST ZONE')">MIDWEST ZONE</div>
                                    <div class="select-option" onclick="selectZone('MINISTRY CENTER ABEOKUTA')">MINISTRY CENTER ABEOKUTA</div>
                                    <div class="select-option" onclick="selectZone('MINISTRY CENTER ABUJA')">MINISTRY CENTER ABUJA</div>
                                    <div class="select-option" onclick="selectZone('MINISTRY CENTER CALABAR')">MINISTRY CENTER CALABAR</div>
                                    <div class="select-option" onclick="selectZone('MINISTRY CENTRE IBADAN')">MINISTRY CENTRE IBADAN</div>
                                    <div class="select-option" onclick="selectZone('MINISTRY CENTRE WARRI')">MINISTRY CENTRE WARRI</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA NORTH CENTRAL ZONE 1')">NIGERIA NORTH CENTRAL ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA NORTH CENTRAL ZONE 2')">NIGERIA NORTH CENTRAL ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA NORTH WEST ZONE 1')">NIGERIA NORTH WEST ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA NORTHERN REGION 1 ZONE 1')">NIGERIA NORTHERN REGION 1 ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA NORTHERN REGION 1 ZONE 2')">NIGERIA NORTHERN REGION 1 ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA SOUTH EAST ZONE 1')">NIGERIA SOUTH EAST ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA SOUTH EAST ZONE 3')">NIGERIA SOUTH EAST ZONE 3</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA SOUTH WEST ZONE 2')">NIGERIA SOUTH WEST ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA SOUTH WEST ZONE 3')">NIGERIA SOUTH WEST ZONE 3</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA SOUTH WEST ZONE 4')">NIGERIA SOUTH WEST ZONE 4</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA SOUTH WEST ZONE 5')">NIGERIA SOUTH WEST ZONE 5</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA SOUTHSOUTH ZONE 3')">NIGERIA SOUTHSOUTH ZONE 3</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA SOUTHSOUTH ZONE 1')">NIGERIA SOUTHSOUTH ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('NIGERIA SOUTHSOUTH ZONE 2')">NIGERIA SOUTHSOUTH ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('ONITSHA')">ONITSHA</div>
                                    <div class="select-option" onclick="selectZone('OTTAWA ZONE')">OTTAWA ZONE</div>
                                    <div class="select-option" onclick="selectZone('PORT HARCOURT ZONE 1')">PORT HARCOURT ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('PORT HARCOURT ZONE 2')">PORT HARCOURT ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('PORTHARCOURT ZONE 3')">PORTHARCOURT ZONE 3</div>
                                    <div class="select-option" onclick="selectZone('QUEBEC ZONE')">QUEBEC ZONE</div>
                                    <div class="select-option" onclick="selectZone('SOUTH AFRICA ZONE 1')">SOUTH AFRICA ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('SOUTH AFRICA ZONE 2')">SOUTH AFRICA ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('SOUTH AFRICA ZONE 3')">SOUTH AFRICA ZONE 3</div>
                                    <div class="select-option" onclick="selectZone('SOUTH AFRICA ZONE 5')">SOUTH AFRICA ZONE 5</div>
                                    <div class="select-option" onclick="selectZone('SOUTH AMERICA REGION')">SOUTH AMERICA REGION</div>
                                    <div class="select-option" onclick="selectZone('TORONTO ZONE')">TORONTO ZONE</div>
                                    <div class="select-option" onclick="selectZone('UK REGION 1 ZONE 1')">UK REGION 1 ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('UK REGION 1 ZONE 2')">UK REGION 1 ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('UK REGION 1 ZONE 3')">UK REGION 1 ZONE 3</div>
                                    <div class="select-option" onclick="selectZone('UK REGION 1 ZONE 4')">UK REGION 1 ZONE 4</div>
                                    <div class="select-option" onclick="selectZone('UK REGION 2 ZONE 1')">UK REGION 2 ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('UK REGION 2 ZONE 3')">UK REGION 2 ZONE 3</div>
                                    <div class="select-option" onclick="selectZone('UK REGION 2 ZONE 4')">UK REGION 2 ZONE 4</div>
                                    <div class="select-option" onclick="selectZone('USA REGION 1 ZONE 1')">USA REGION 1 ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('USA REGION 1 ZONE 2')">USA REGION 1 ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('USA REGION 2')">USA REGION 2</div>
                                    <div class="select-option" onclick="selectZone('USA REGION 3')">USA REGION 3</div>
                                    <div class="select-option" onclick="selectZone('WESTERN EUROPE ZONE 1')">WESTERN EUROPE ZONE 1</div>
                                    <div class="select-option" onclick="selectZone('WESTERN EUROPE ZONE 2')">WESTERN EUROPE ZONE 2</div>
                                    <div class="select-option" onclick="selectZone('WESTERN EUROPE ZONE 3')">WESTERN EUROPE ZONE 3</div>
                                    <div class="select-option" onclick="selectZone('WESTERN EUROPE ZONE 4')">WESTERN EUROPE ZONE 4</div>
                                    <div class="select-option" onclick="selectZone('BLW Benin Republic Zone')">BLW Benin Republic Zone</div>
                                    <div class="select-option" onclick="selectZone('BLW Burkina Faso')">BLW Burkina Faso</div>
                                    <div class="select-option" onclick="selectZone('BLW Cameroon Zone')">BLW Cameroon Zone</div>
                                    <div class="select-option" onclick="selectZone('BLW Canada Region')">BLW Canada Region</div>
                                    <div class="select-option" onclick="selectZone('BLW DR Congo')">BLW DR Congo</div>
                                    <div class="select-option" onclick="selectZone('BLW Ghana Zone A')">BLW Ghana Zone A</div>
                                    <div class="select-option" onclick="selectZone('BLW Ghana Zone B')">BLW Ghana Zone B</div>
                                    <div class="select-option" onclick="selectZone('BLW Ghana Zone C')">BLW Ghana Zone C</div>
                                    <div class="select-option" onclick="selectZone('BLW Ghana Zone D')">BLW Ghana Zone D</div>
                                    <div class="select-option" onclick="selectZone('BLW Ireland Zone')">BLW Ireland Zone</div>
                                    <div class="select-option" onclick="selectZone('BLW Kenya Zone')">BLW Kenya Zone</div>
                                    <div class="select-option" onclick="selectZone('BLW Middle East and North Africa Region')">BLW Middle East and North Africa Region</div>
                                    <div class="select-option" onclick="selectZone('BLW SA Zone A')">BLW SA Zone A</div>
                                    <div class="select-option" onclick="selectZone('BLW SA Zone B')">BLW SA Zone B</div>
                                    <div class="select-option" onclick="selectZone('BLW SA Zone C')">BLW SA Zone C</div>
                                    <div class="select-option" onclick="selectZone('BLW SA Zone D')">BLW SA Zone D</div>
                                    <div class="select-option" onclick="selectZone('BLW SA Zone E')">BLW SA Zone E</div>
                                    <div class="select-option" onclick="selectZone('BLW SA Zone F')">BLW SA Zone F</div>
                                    <div class="select-option" onclick="selectZone('BLW SA Zone G')">BLW SA Zone G</div>
                                    <div class="select-option" onclick="selectZone('BLW Tanzania')">BLW Tanzania</div>
                                    <div class="select-option" onclick="selectZone('BLW Uganda Zone A')">BLW Uganda Zone A</div>
                                    <div class="select-option" onclick="selectZone('BLW Uganda Zone B')">BLW Uganda Zone B</div>
                                    <div class="select-option" onclick="selectZone('BLW UK Zone A')">BLW UK Zone A</div>
                                    <div class="select-option" onclick="selectZone('BLW UK Zone B')">BLW UK Zone B</div>
                                    <div class="select-option" onclick="selectZone('BLW UK Zone C')">BLW UK Zone C</div>
                                    <div class="select-option" onclick="selectZone('BLW USA Region 1')">BLW USA Region 1</div>
                                    <div class="select-option" onclick="selectZone('BLW USA Region 2')">BLW USA Region 2</div>
                                    <div class="select-option" onclick="selectZone('BLW Wales Zone')">BLW Wales Zone</div>
                                    <div class="select-option" onclick="selectZone('BLW West Africa Region')">BLW West Africa Region</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone A')">BLW Zone A</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone B')">BLW Zone B</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone C')">BLW Zone C</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone D')">BLW Zone D</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone E')">BLW Zone E</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone F')">BLW Zone F</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone G')">BLW Zone G</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone H')">BLW Zone H</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone I')">BLW Zone I</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone J')">BLW Zone J</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone K')">BLW Zone K</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone L')">BLW Zone L</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone M')">BLW Zone M</div>
                                    <div class="select-option" onclick="selectZone('BLW Zone N')">BLW Zone N</div>
                                    <div class="select-option" onclick="selectZone('Loveworld Singes HQ')">Loveworld Singes HQ</div>
                                    <div class="select-option" onclick="selectZone('PMC HQ')">PMC HQ</div>
                                    <div class="select-option" onclick="selectZone('24 Worship Band HQ')">24 Worship Band HQ</div>
                                    <div class="select-option" onclick="selectZone('Orchestra HQ')">Orchestra HQ</div>
                                    <div class="select-option" onclick="selectZone('LGF')">LGF</div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <input type="text" name="church" class="form-control form-control-dark" value="<?php echo $active_tab === 'choir' ? htmlspecialchars($church) : ''; ?>" placeholder="Church">
                            </div>
                            <div class="d-grid mt-4">
                                <button type="submit" name="account_type" value="choir" class="btn btn-primary-custom">Register</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <p>Already have an account? <a href="login.php">Login</a></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function setupPasswordToggle(toggleButtonId, passwordInputId) {
                const toggleButton = document.getElementById(toggleButtonId);
                const passwordInput = document.getElementById(passwordInputId);

                if (toggleButton && passwordInput) {
                    toggleButton.addEventListener('click', function() {
                        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                        passwordInput.setAttribute('type', type);
                        this.querySelector('i').classList.toggle('fa-eye');
                        this.querySelector('i').classList.toggle('fa-eye-slash');
                    });
                }
            }

            setupPasswordToggle('togglePasswordIndividual', 'password_individual');
            setupPasswordToggle('toggleConfirmPasswordIndividual', 'confirm_password_individual');
            setupPasswordToggle('togglePasswordChoir', 'password_choir');
            setupPasswordToggle('toggleConfirmPasswordChoir', 'confirm_password_choir');

            let activeDropdownInput = null;

            function setupDropdown(inputId) {
                const input = document.getElementById(inputId);
                if (!input) return;

                const dropdownId = input.getAttribute('data-dropdown');
                const dropdown = document.getElementById(dropdownId);
                if (!dropdown) return;

                const options = Array.from(dropdown.querySelectorAll('.select-option'));

                function showDropdown() {
                    dropdown.classList.remove('hidden');
                }

                function hideDropdown() {
                    dropdown.classList.add('hidden');
                }

                function filterOptions() {
                    const term = input.value.toLowerCase();
                    options.forEach(option => {
                        const match = option.textContent.toLowerCase().includes(term);
                        option.style.display = match ? '' : 'none';
                    });
                }

                input.addEventListener('focus', function() {
                    activeDropdownInput = input;
                    showDropdown();
                    filterOptions();
                });

                input.addEventListener('click', function() {
                    activeDropdownInput = input;
                    showDropdown();
                    filterOptions();
                });

                input.addEventListener('input', function() {
                    activeDropdownInput = input;
                    showDropdown();
                    filterOptions();
                });

                dropdown.addEventListener('click', function(event) {
                    const option = event.target.closest('.select-option');
                    if (!option) return;
                    activeDropdownInput = input;
                    input.value = option.textContent.trim();
                    hideDropdown();
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                });

                document.addEventListener('click', function(event) {
                    if (!input.contains(event.target) && !dropdown.contains(event.target)) {
                        hideDropdown();
                    }
                });
            }

            window.selectZone = function(value) {
                if (!activeDropdownInput) return;

                const dropdownId = activeDropdownInput.getAttribute('data-dropdown');
                const dropdown = document.getElementById(dropdownId);
                activeDropdownInput.value = value;
                if (dropdown) {
                    dropdown.classList.add('hidden');
                }
                activeDropdownInput.dispatchEvent(new Event('input', { bubbles: true }));
            };

            setupDropdown('satellite_campus_individual');
            setupDropdown('satellite_campus_choir');
            setupDropdown('country_individual');
            setupDropdown('country_choir');
        });
    </script>
    <?php include 'includes/footer.php'; ?>
