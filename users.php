<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$pageTitle = 'Owner Account';
$ownerName = $_SESSION['name'] ?? 'Store owner';
$username = $_SESSION['username'] ?? '';
$initial = strtoupper(substr($ownerName, 0, 1));

require __DIR__ . '/includes/header.php';
?>

<style>
    .owner-account {
        max-width: 42rem;
    }

    .owner-account__identity {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .owner-account__avatar {
        display: grid;
        flex: 0 0 3.25rem;
        width: 3.25rem;
        height: 3.25rem;
        place-items: center;
        border-radius: 50%;
        background: #fcebe2;
        color: #a74725;
        font-size: 1.25rem;
        font-weight: 700;
    }

    .owner-account__identity h2,
    .owner-account__identity p {
        margin: 0;
    }

    .owner-account__identity h2 {
        font-size: 1.25rem;
        overflow-wrap: anywhere;
    }

    .owner-account__details {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
        margin: 1.25rem 0 0;
        padding-top: 1.25rem;
        border-top: 1px solid #131313;
    }

    .owner-account__details dt {
        font-size: 0.875rem;
    }

    .owner-account__details dd {
        margin: 0.35rem 0 0;
        font-weight: 600;
        overflow-wrap: anywhere;
    }

    @media (max-width: 480px) {
        .owner-account__details {
            grid-template-columns: 1fr;
        }
    }
</style>

<section class="panel owner-account" aria-labelledby="owner-name">
    <div class="owner-account__identity">
        <span class="owner-account__avatar" aria-hidden="true"><?= e($initial) ?></span>
        <div>
            <h2 id="owner-name"><?= e($ownerName) ?></h2>
            <p class="muted">Store owner</p>
        </div>
    </div>

    <dl class="owner-account__details">
        <div>
            <dt class="muted">Full name</dt>
            <dd><?= e($ownerName) ?></dd>
        </div>
        <div>
            <dt class="muted">Username</dt>
            <dd><?= e($username !== '' ? $username : '—') ?></dd>
        </div>
    </dl>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>