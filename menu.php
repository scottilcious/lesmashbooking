<?php 
// Start the session
session_start();  

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) ) {
  // If not logged in, redirect to the login page
  header("Location: login.php");
  exit();
}

include 'includes/functions.php';

$userId = $_SESSION['user_id'];
$memberNumber = $_SESSION['member_number'];
$memberType = $_SESSION['member_type'];


$memberData = get_member_info($userId, $memberType);
$memberCredit = $memberData['credit'];
$memberPhone = $memberData['member_phone'];
//$memberType = $memberData['member_type'];


?>

<header class="p-3 border-bottom text-bg-light" <?= $_SESSION['member_number'] ?>>
    <div class="container">
      <div class="row align-items-center">

        <div class="col-12 col-md-1">
          <a href="index.php" class="d-flex align-items-center mb-2 mb-lg-0 link-body-emphasis text-decoration-none">
            <img src="images/logo.png" alt="" width="60">
          </a>
        </div>

        <div class="col-12 <?= ( $memberType == 'admin')? "col-md-9": "col-md-6" ?>">
          <nav class="main-nav">
            <a href="index.php" class="menu-item page-id-1"><i class="ri-calendar-schedule-fill"></i> Make a booking</a>
            <?php if( $memberType  == 'admin' ): ?>
              <a href="admin-bookings.php" class="menu-item page-id-2"><i class="ri-calendar-check-fill"></i>All bookings</a>
              <!-- <a href="admin-waitlist.php" class="menu-item page-id-6"><i class="ri-time-fill"></i>Waitlist</a> -->
            <?php else: ?>
              <a href="bookings.php" class="menu-item page-id-2"><i class="ri-calendar-check-fill"></i>My bookings</a>
            <?php endif; ?>
            <?php if( $memberType == 'admin'): ?>
              <a href="admin-members.php" class="menu-item page-id-3"><i class="ri-team-fill"></i> All members</a>
              <a href="admin-add-member.php" class="menu-item page-id-4"><i class="ri-user-add-fill"></i> Add member</a>
              <a href="admin-transactions.php" class="menu-item page-id-5"><i class="ri-wallet-2-fill"></i>Transactions</a>
              <a href="admin-add-credit.php" class="menu-item page-id-7"><i class="ri-apps-2-add-fill"></i> Add credit</a>
              <a href="admin-logs.php" class="menu-item page-id-8"><i class="ri-file-list-3-fill"></i> Logs</a>
            <?php endif; ?>
          </nav>
        </div>

        <div class="col-12 <?= ( $memberType == 'admin')? "col-md-2": "col-md-5" ?> text-end">

          <?php if( $memberType == 'non-member'): ?>
          <!--  Non member -->
            <div class="row">
              <div class="col text-end">
                <span class="badge rounded-pill text-bg-dark">Non Member</span><br>
                <?= $_SESSION["member_fullname"] ?>
              </div>
              <div class="col-auto text-end border-start">
                <a href="logout.php" class="btn btn-outline-dark"><i class="ri-logout-box-r-line"></i> Sign out</a>
              </div>
            </div>

          <?php elseif( $memberType == 'admin'): ?>
          <!--  Admin -->
          <div class="row align-items-center">
            <div class="col text-end">
              <div class="dropdown">
                <button class="btn btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                  Admin
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><a class="dropdown-item" href="admin-settings.php"><i class="ri-settings-3-line"></i> Settings</a></li>
                  <li><hr class="dropdown-divider"></li>
                  <li><a class="dropdown-item" href="logout.php"><i class="ri-logout-box-r-line"></i> Sign out</a></li>
                </ul>
              </div>
            </div>
          </div>
          <?php else: ?>
          <!--  Member -->
          <div class="row">
            <div class="col">

              <span class="fw-bold"><?= $memberData['first_name']; ?> <?= $memberData['last_name']; ?></span> <span class="badge text-bg-light"> (<?= $memberData['member_number']; ?>)</span> <span class="badge rounded-pill text-bg-primary"><?= $memberData['member_type']; ?></span><br>
              <small>Remaining credit: </small> <span class="badge rounded-pill text-bg-dark">
                <?php echo credit_number_sanitize($memberCredit); ?>
              </span>
              <a id="triggerAddCredit" class="btn btn-link" href="add-credit.php">Add credit</a>

            </div>
            <div class="col-auto text-end">
              <small>Membership expiry: </small><br>
              <?= lsc_format_date( 'db_to_readable', $memberData['member_expiration']) ; ?><br>
              <a id="triggerAddCredit" class="btn btn-link" href="member-renew.php">Renew Membership</a>
              
            </div>
            <div class="col-auto text-end border-start">
              <a href="user-edit.php" class="btn btn-link">Edit Profile</a><br>
              <a href="logout.php" class="btn btn-outline-dark"><i class="ri-logout-box-r-line"></i> Sign out</a>
            </div>
          </div>
          
          <?php endif; ?>
            
        </div>
      </div>

    </div>
  </header>


 

