<?php
session_start();

$formStatus = $_SESSION['trip_form_status'] ?? '';
unset($_SESSION['trip_form_status']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $style = trim($_POST['style'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($name === '' || strlen($name) > 120) {
        $_SESSION['trip_form_status'] = 'Please enter a name no longer than 120 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 180) {
        $_SESSION['trip_form_status'] = 'Please enter a valid email address no longer than 180 characters.';
    } elseif ($date !== '' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $date)) {
        $_SESSION['trip_form_status'] = 'Please choose a valid preferred travel month.';
    } elseif (strlen($notes) > 10000) {
        $_SESSION['trip_form_status'] = 'Please keep your notes under 10,000 characters.';
    } else {
        $message = implode("\n", [
            'Preferred travel month: ' . ($date !== '' ? $date : 'Flexible'),
            'Experience: ' . ($style !== '' ? $style : 'General enquiry'),
            'Notes: ' . ($notes !== '' ? $notes : 'None'),
        ]);

        try {
            require __DIR__ . '/config/connect.php';
            $stmt = $conn->prepare(
                "INSERT INTO inquiries (name, email, phone, message) VALUES (?, ?, '', ?)"
            );
            $stmt->bind_param('sss', $name, $email, $message);
            $stmt->execute();
            $stmt->close();
            $_SESSION['trip_form_status'] = 'Thank you. Your trip request has been sent successfully.';
        } catch (mysqli_sql_exception $exception) {
            error_log('Trip request database error: ' . $exception->getMessage());
            $_SESSION['trip_form_status'] = 'We could not save your request right now. Please try again later.';
        }
    }

    header('Location: index.php#contact');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f7f6f0">
    <meta name="description"
        content="Discover Uganda at your own pace with KIKOMA Tour & Travel. Explore unforgettable safaris, gorilla trekking, cultural encounters and tailor-made journeys.">
    <title>KIKOMA Tour & Travel — Find your wild</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --ink: #20291f;
            --muted: #77796e;
            --paper: #f7f6f0;
            --white: #fffefa;
            --green: #314c35;
            --green-dark: #253c29;
            --lime: #c8d18a;
            --orange: #d88651;
            --line: #e7e5dc;
            --serif: "Playfair Display", Georgia, serif;
            --sans: "DM Sans", Arial, sans-serif;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 88px;
        }

        body {
            margin: 0;
            color: var(--ink);
            background: var(--paper);
            font-family: var(--sans);
            -webkit-font-smoothing: antialiased;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input,
        select,
        textarea {
            font: inherit;
        }

        button,
        a {
            -webkit-tap-highlight-color: transparent;
        }

        img {
            display: block;
            width: 100%;
        }

        .container {
            width: min(1160px, calc(100% - 64px));
            margin-inline: auto;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--green);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .16em;
            text-transform: uppercase;
        }

        .eyebrow::before {
            width: 22px;
            height: 1px;
            background: var(--orange);
            content: "";
        }

        .section-heading {
            max-width: 650px;
            margin: 15px 0 0;
            font: 500 clamp(36px, 5vw, 57px)/1.1 var(--serif);
            letter-spacing: -.035em;
        }

        .section-copy {
            max-width: 490px;
            margin: 18px 0 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.8;
        }

        .topbar {
            min-height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 7px 18px;
            color: #f9f7ee;
            background: var(--green-dark);
            font-size: 11px;
            letter-spacing: .035em;
            text-align: center;
        }

        .topbar strong {
            margin-right: 5px;
            color: var(--lime);
            font-weight: 600;
        }

        .header {
            position: sticky;
            z-index: 10;
            top: 0;
            background: rgba(247, 246, 240, .96);
            border-bottom: 1px solid rgba(32, 41, 31, .07);
            backdrop-filter: blur(12px);
        }

        .nav {
            min-height: 76px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 26px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            flex-shrink: 0;
        }

        .brand-mark {
            width: 39px;
            height: 39px;
            display: grid;
            place-items: center;
            border: 1px solid rgba(49, 76, 53, .3);
            border-radius: 50%;
            color: var(--green);
        }

        .brand-mark svg {
            width: 24px;
            height: 24px;
        }

        .brand-name {
            display: block;
            color: var(--ink);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .11em;
        }

        .brand-caption {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            font-size: 9px;
            letter-spacing: .18em;
            text-transform: uppercase;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: clamp(17px, 3vw, 36px);
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .nav-links a {
            color: #57594f;
            font-size: 12px;
            transition: color .2s;
        }

        .nav-links a:hover {
            color: var(--orange);
        }

        .button {
            min-height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 0 20px;
            border: 1px solid transparent;
            border-radius: 3px;
            background: var(--green);
            color: white;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: background .2s, transform .2s;
        }

        .button:hover {
            transform: translateY(-2px);
            background: var(--green-dark);
        }

        .button svg {
            width: 15px;
            height: 15px;
        }

        .button-outline {
            border-color: rgba(255, 255, 255, .7);
            background: transparent;
        }

        .button-outline:hover {
            background: white;
            color: var(--ink);
        }

        .menu-toggle {
            display: none;
            width: 42px;
            height: 42px;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--line);
            border-radius: 3px;
            background: transparent;
            color: var(--ink);
            cursor: pointer;
        }

        .menu-toggle svg {
            width: 20px;
            height: 20px;
        }

        .hero {
            position: relative;
            min-height: 610px;
            display: flex;
            align-items: center;
            overflow: hidden;
            color: white;
            background: #39483a url("https://images.unsplash.com/photo-1516426122078-c23e76319801?auto=format&fit=crop&w=2200&q=85") center 49%/cover no-repeat;
        }

        .hero::before {
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(17, 27, 19, .77) 0%, rgba(17, 27, 19, .51) 48%, rgba(17, 27, 19, .1) 100%), linear-gradient(0deg, rgba(17, 27, 19, .31), transparent 40%);
            content: "";
        }

        .hero-content {
            position: relative;
            z-index: 1;
            padding-top: 38px;
            padding-bottom: 50px;
        }

        .hero .eyebrow {
            color: #e4e6cb;
        }

        .hero h1 {
            max-width: 680px;
            margin: 22px 0 0;
            font: 500 clamp(53px, 8vw, 94px)/.99 var(--serif);
            letter-spacing: -.048em;
        }

        .hero h1 em {
            color: var(--lime);
            font-weight: 500;
        }

        .hero-copy {
            max-width: 440px;
            margin: 23px 0 0;
            color: rgba(255, 255, 255, .83);
            font-size: 14px;
            line-height: 1.85;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 31px;
        }

        .hero-note {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 39px;
            color: rgba(255, 255, 255, .8);
            font-size: 11px;
        }

        .hero-note::before {
            width: 27px;
            height: 1px;
            background: var(--lime);
            content: "";
        }

        .hero-side-note {
            position: absolute;
            right: max(32px, calc((100vw - 1160px) / 2));
            bottom: 40px;
            z-index: 1;
            color: rgba(255, 255, 255, .82);
            font-size: 10px;
            letter-spacing: .12em;
            text-transform: uppercase;
            writing-mode: vertical-rl;
        }

        .intro {
            padding: 82px 0 87px;
        }

        .intro-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            align-items: center;
            gap: 80px;
        }

        .intro-text .section-heading {
            max-width: 520px;
        }

        .intro-text .section-copy {
            max-width: 465px;
        }

        .text-link {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            margin-top: 25px;
            color: var(--green);
            font-size: 12px;
            font-weight: 700;
        }

        .text-link svg {
            width: 15px;
            height: 15px;
            transition: transform .2s;
        }

        .text-link:hover svg {
            transform: translateX(4px);
        }

        .intro-image {
            position: relative;
            padding: 0 0 26px 27px;
        }

        .intro-image::before {
            position: absolute;
            z-index: -1;
            inset: 25px 25px 0 0;
            border: 1px solid #d8d8c9;
            content: "";
        }

        .intro-image img {
            height: 330px;
            object-fit: cover;
        }

        .image-caption {
            position: absolute;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 14px 17px;
            background: var(--white);
            box-shadow: 0 5px 25px rgba(32, 41, 31, .09);
        }

        .image-caption-number {
            color: var(--orange);
            font: 500 26px/1 var(--serif);
        }

        .image-caption-text {
            color: var(--muted);
            font-size: 10px;
            line-height: 1.55;
        }

        .experiences {
            padding: 85px 0 95px;
            background: #efeee6;
        }

        .section-top {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 26px;
        }

        .section-top .section-heading {
            max-width: 600px;
        }

        .section-top .section-copy {
            max-width: 330px;
            margin-bottom: 5px;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 39px;
        }

        .experience-card {
            min-width: 0;
            background: var(--white);
        }

        .card-photo {
            position: relative;
            overflow: hidden;
        }

        .card-photo img {
            height: 235px;
            object-fit: cover;
            transition: transform .6s cubic-bezier(.2, .7, .2, 1);
        }

        .experience-card:hover .card-photo img {
            transform: scale(1.045);
        }

        .card-tag {
            position: absolute;
            top: 15px;
            left: 15px;
            padding: 7px 10px;
            background: rgba(255, 254, 250, .94);
            color: var(--green);
            font-size: 9px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .card-content {
            padding: 21px 21px 24px;
        }

        .card-content h3 {
            margin: 0;
            font: 500 24px/1.2 var(--serif);
        }

        .card-content p {
            min-height: 49px;
            margin: 10px 0 15px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.7;
        }

        .card-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 13px;
            border-top: 1px solid var(--line);
            color: #6e7066;
            font-size: 10px;
        }

        .card-bottom a {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: var(--green);
            font-weight: 700;
        }

        .card-bottom svg {
            width: 13px;
            height: 13px;
        }

        .quote-band {
            position: relative;
            overflow: hidden;
            padding: 79px 24px 83px;
            color: white;
            background: var(--green);
            text-align: center;
        }

        .quote-band::before,
        .quote-band::after {
            position: absolute;
            width: 260px;
            height: 260px;
            border: 1px solid rgba(255, 255, 255, .09);
            border-radius: 50%;
            content: "";
        }

        .quote-band::before {
            top: -180px;
            left: -70px;
        }

        .quote-band::after {
            right: -80px;
            bottom: -185px;
            width: 340px;
            height: 340px;
        }

        .quote-band .eyebrow {
            color: var(--lime);
        }

        .quote-band .eyebrow::before {
            background: var(--orange);
        }

        .quote-band blockquote {
            position: relative;
            max-width: 780px;
            margin: 21px auto 0;
            font: 500 clamp(28px, 4.1vw, 46px)/1.28 var(--serif);
            letter-spacing: -.02em;
        }

        .quote-credit {
            margin-top: 21px;
            color: rgba(255, 255, 255, .65);
            font-size: 10px;
            letter-spacing: .13em;
            text-transform: uppercase;
        }

        .why {
            padding: 91px 0 93px;
        }

        .why-layout {
            display: grid;
            grid-template-columns: .9fr 1.1fr;
            gap: 83px;
            align-items: start;
        }

        .why-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px 30px;
            padding-top: 13px;
        }

        .why-item {
            padding-top: 16px;
            border-top: 1px solid var(--line);
        }

        .why-icon {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            margin-bottom: 14px;
            border-radius: 50%;
            background: #e9ecda;
            color: var(--green);
        }

        .why-icon svg {
            width: 17px;
            height: 17px;
        }

        .why-item h3 {
            margin: 0;
            font: 500 19px/1.3 var(--serif);
        }

        .why-item p {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.75;
        }

        .journey {
            padding: 82px 0 92px;
            background: #efeee6;
        }

        .journey .section-heading {
            max-width: 560px;
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 28px;
            margin-top: 39px;
        }

        .step {
            position: relative;
            padding: 24px 22px 0 0;
            border-top: 1px solid #d6d5c9;
        }

        .step-number {
            color: var(--orange);
            font: 500 26px/1 var(--serif);
        }

        .step h3 {
            margin: 15px 0 0;
            font: 500 22px/1.25 var(--serif);
        }

        .step p {
            max-width: 280px;
            margin: 9px 0 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.75;
        }

        .contact {
            padding: 88px 0;
        }

        .contact-panel {
            display: grid;
            grid-template-columns: .9fr 1.1fr;
            overflow: hidden;
            background: var(--white);
            box-shadow: 0 12px 50px rgba(32, 41, 31, .055);
        }

        .contact-copy {
            padding: 46px 42px;
            color: white;
            background: var(--green);
        }

        .contact-copy .eyebrow {
            color: var(--lime);
        }

        .contact-copy h2 {
            margin: 19px 0 0;
            font: 500 clamp(35px, 4vw, 48px)/1.1 var(--serif);
            letter-spacing: -.03em;
        }

        .contact-copy>p {
            max-width: 345px;
            margin: 15px 0 0;
            color: rgba(255, 255, 255, .76);
            font-size: 12px;
            line-height: 1.8;
        }

        .contact-aside {
            margin-top: 35px;
            padding-top: 19px;
            border-top: 1px solid rgba(255, 255, 255, .2);
            color: rgba(255, 255, 255, .8);
            font-size: 10px;
            line-height: 1.8;
        }

        .contact-aside strong {
            display: block;
            margin-bottom: 3px;
            color: var(--lime);
            font-weight: 600;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .contact-form {
            padding: 42px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px 15px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .field-full {
            grid-column: 1/-1;
        }

        .field label {
            color: #56594f;
            font-size: 10px;
            font-weight: 700;
        }

        .field input,
        .field select,
        .field textarea {
            width: 100%;
            min-height: 44px;
            padding: 11px 12px;
            border: 1px solid var(--line);
            border-radius: 2px;
            outline: none;
            background: #fffefa;
            color: var(--ink);
            font-size: 12px;
            transition: border-color .2s;
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            border-color: var(--green);
        }

        .field textarea {
            min-height: 82px;
            resize: vertical;
        }

        .form-submit {
            margin-top: 20px;
        }

        .form-note,
        .form-status {
            margin: 12px 0 0;
            color: var(--muted);
            font-size: 10px;
            line-height: 1.6;
        }

        .form-status {
            color: var(--green);
            font-size: 12px;
            font-weight: 600;
        }

        .form-status:empty {
            display: none;
        }

        .faq {
            padding: 77px 0 86px;
            background: #efeee6;
        }

        .faq-layout {
            display: grid;
            grid-template-columns: .8fr 1.2fr;
            gap: 85px;
        }

        .faq-list {
            border-top: 1px solid #d6d5c9;
        }

        .faq-item {
            border-bottom: 1px solid #d6d5c9;
        }

        .faq-question {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 19px 0;
            border: 0;
            background: transparent;
            color: var(--ink);
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            text-align: left;
        }

        .faq-question svg {
            width: 17px;
            height: 17px;
            flex: 0 0 auto;
            color: var(--green);
            transition: transform .2s;
        }

        .faq-question[aria-expanded="true"] svg {
            transform: rotate(45deg);
        }

        .faq-answer {
            max-width: 600px;
            padding: 0 32px 17px 0;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.8;
        }

        .faq-answer[hidden] {
            display: none;
        }

        .footer {
            padding: 36px 0 24px;
            color: rgba(255, 255, 255, .73);
            background: #202b21;
        }

        .footer-main {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
            padding-bottom: 27px;
        }

        .footer .brand-name {
            color: white;
        }

        .footer .brand-caption {
            color: rgba(255, 255, 255, .55);
        }

        .footer .brand-mark {
            border-color: rgba(255, 255, 255, .3);
            color: var(--lime);
        }

        .footer-links {
            display: flex;
            flex-wrap: wrap;
            gap: 22px;
        }

        .footer-links a {
            font-size: 10px;
            transition: color .2s;
        }

        .footer-links a:hover {
            color: var(--lime);
        }

        .footer-bottom {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding-top: 18px;
            border-top: 1px solid rgba(255, 255, 255, .14);
            color: rgba(255, 255, 255, .48);
            font-size: 9px;
        }

        @media (min-width: 1500px) {
            .hero {
                min-height: 680px;
            }
        }

        @media (max-width: 900px) {
            .container {
                width: min(100% - 48px, 700px);
            }

            .nav {
                min-height: 68px;
            }

            .nav-links {
                gap: 16px;
            }

            .nav-links a {
                font-size: 11px;
            }

            .nav>.button {
                min-height: 40px;
                padding-inline: 13px;
                font-size: 11px;
            }

            .intro-grid,
            .why-layout {
                gap: 42px;
            }

            .card-photo img {
                height: 200px;
            }

            .card-content {
                padding-inline: 16px;
            }

            .contact-copy {
                padding: 37px 28px;
            }

            .contact-form {
                padding: 34px 27px;
            }

            .faq-layout {
                gap: 44px;
            }
        }

        @media (max-width: 680px) {
            .container {
                width: calc(100% - 40px);
            }

            .topbar {
                min-height: 33px;
                font-size: 9px;
            }

            .nav {
                min-height: 65px;
            }

            .menu-toggle {
                display: inline-flex;
            }

            .nav>.button {
                display: none;
            }

            .nav-links {
                position: absolute;
                top: 100%;
                right: 0;
                left: 0;
                display: none;
                align-items: stretch;
                gap: 0;
                padding: 8px 20px 17px;
                border-bottom: 1px solid var(--line);
                background: var(--paper);
            }

            .nav-links.is-open {
                display: flex;
                flex-direction: column;
            }

            .nav-links li a {
                display: block;
                padding: 12px 0;
                border-bottom: 1px solid rgba(32, 41, 31, .07);
                font-size: 13px;
            }

            .hero {
                min-height: 590px;
                background-position: 57% center;
            }

            .hero::before {
                background: linear-gradient(90deg, rgba(17, 27, 19, .76), rgba(17, 27, 19, .35)), linear-gradient(0deg, rgba(17, 27, 19, .31), transparent 55%);
            }

            .hero-content {
                padding-top: 10px;
            }

            .hero h1 {
                font-size: clamp(55px, 14vw, 77px);
            }

            .hero-copy {
                max-width: 370px;
                font-size: 13px;
            }

            .hero-side-note {
                right: 20px;
                bottom: 21px;
                font-size: 8px;
            }

            .intro,
            .why,
            .contact {
                padding: 66px 0;
            }

            .intro-grid,
            .why-layout,
            .faq-layout {
                grid-template-columns: 1fr;
                gap: 35px;
            }

            .intro-image {
                max-width: 500px;
            }

            .intro-image img {
                height: 280px;
            }

            .section-top {
                align-items: start;
                flex-direction: column;
                gap: 9px;
            }

            .section-top .section-copy {
                margin-top: 8px;
            }

            .experiences,
            .journey,
            .faq {
                padding: 67px 0;
            }

            .cards {
                grid-template-columns: 1fr;
                gap: 16px;
                margin-top: 27px;
            }

            .card-photo img {
                height: 245px;
            }

            .card-content p {
                min-height: auto;
            }

            .quote-band {
                padding: 62px 22px;
            }

            .why-layout {
                gap: 27px;
            }

            .why-list {
                gap: 23px 17px;
            }

            .steps {
                grid-template-columns: 1fr;
                gap: 23px;
                margin-top: 29px;
            }

            .step {
                padding-top: 17px;
            }

            .contact-panel {
                grid-template-columns: 1fr;
            }

            .contact-copy {
                padding: 33px 25px;
            }

            .contact-aside {
                margin-top: 23px;
            }

            .contact-form {
                padding: 28px 22px;
            }

            .faq-layout {
                gap: 26px;
            }

            .footer-main,
            .footer-bottom {
                align-items: flex-start;
                flex-direction: column;
            }

            .footer-main {
                gap: 21px;
            }

            .footer-links {
                gap: 15px;
            }
        }

        @media (max-width: 390px) {
            .container {
                width: calc(100% - 32px);
            }

            .hero-actions {
                align-items: stretch;
                flex-direction: column;
                max-width: 230px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .field-full {
                grid-column: auto;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>

<body>
    <div class="topbar"><strong>Uganda is calling.</strong> Let’s find the journey that feels like yours.</div>

    <header class="header">
        <nav class="nav container" aria-label="Main navigation">
            <a class="brand" href="#home" aria-label="KIKOMA Tour and Travel home">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 32 32" fill="none">
                        <path d="M6 23.5 15.8 7l9.8 16.5H6Z" stroke="currentColor" stroke-width="1.5"
                            stroke-linejoin="round" />
                        <path d="m11.3 23.5 4.5-7.4 4.5 7.4M4 26h24" stroke="currentColor" stroke-width="1.5"
                            stroke-linecap="round" />
                    </svg>
                </span>
                <span><span class="brand-name">KIKOMA</span><span class="brand-caption">Tour &amp; Travel</span></span>
            </a>
            <ul class="nav-links" id="nav-links">
                <li><a href="#experiences">Experiences</a></li>
                <li><a href="#about">Our approach</a></li>
                <li><a href="#journey">How it works</a></li>
                <li><a href="#faq">FAQs</a></li>
            </ul>
            <a class="button" href="#contact">Plan your trip <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M4 10h12m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"
                        stroke-linejoin="round" />
                </svg></a>
            <button class="menu-toggle" type="button" aria-label="Open navigation menu" aria-expanded="false"
                aria-controls="nav-links">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                </svg>
            </button>
        </nav>
    </header>

    <main>
        <section class="hero" id="home" aria-labelledby="hero-title">
            <div class="container hero-content">
                <span class="eyebrow">Small moments. Big journeys.</span>
                <h1 id="hero-title">Find your<br><em>wild.</em></h1>
                <p class="hero-copy">From misty mountain forests to the wide-open savannah, discover Uganda with
                    journeys made thoughtfully, personally, and at your pace.</p>
                <div class="hero-actions">
                    <a class="button" href="#experiences">Explore experiences <svg viewBox="0 0 20 20" fill="none"
                            aria-hidden="true">
                            <path d="M4 10h12m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.6"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg></a>
                    <a class="button button-outline" href="#about">Get to know us</a>
                </div>
                <div class="hero-note">Your Uganda story starts here</div>
            </div>
            <div class="hero-side-note" aria-hidden="true">The Pearl of Africa · 01°22′N</div>
        </section>

        <section class="intro" id="about">
            <div class="container intro-grid">
                <div class="intro-text">
                    <span class="eyebrow">More than a destination</span>
                    <h2 class="section-heading">Come for the wild. Leave with a story.</h2>
                    <p class="section-copy">Uganda has a way of staying with you. The stillness of the forest. The
                        warmth of a welcome. The moment the landscape opens up before you. We’ll help you experience it
                        in a way that feels entirely your own.</p>
                    <a class="text-link" href="#contact">Let’s start planning <svg viewBox="0 0 20 20" fill="none"
                            aria-hidden="true">
                            <path d="M4 10h12m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.6"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg></a>
                </div>
                <div class="intro-image">
                    <img src="https://images.unsplash.com/photo-1516026672322-bc52d61a55d5?auto=format&fit=crop&w=1100&q=80"
                        alt="A glimpse of Uganda's lush tropical landscape" loading="lazy">
                    <div class="image-caption"><span class="image-caption-number">01</span><span
                            class="image-caption-text">One country.<br>So many ways to feel it.</span></div>
                </div>
            </div>
        </section>

        <section class="experiences" id="experiences">
            <div class="container">
                <div class="section-top">
                    <div>
                        <span class="eyebrow">Choose your kind of adventure</span>
                        <h2 class="section-heading">A little bit of everything. A lot to remember.</h2>
                    </div>
                    <p class="section-copy">Big adventures, quiet moments, and all the in-between. Tell us what moves
                        you and we’ll help map it out.</p>
                </div>
                <div class="cards">
                    <article class="experience-card">
                        <div class="card-photo">
                            <img src="https://images.unsplash.com/photo-1547970810-dc1eac37d174?auto=format&fit=crop&w=1000&q=80"
                                alt="Elephants walking across an African savannah" loading="lazy">
                            <span class="card-tag">Wildlife &amp; nature</span>
                        </div>
                        <div class="card-content">
                            <h3>Into the wild</h3>
                            <p>Follow the rhythm of the bush on a memorable safari through Uganda’s remarkable
                                landscapes.</p>
                            <div class="card-bottom"><span>Safari · National parks</span><a href="#contact"
                                    aria-label="Plan a wildlife and nature safari">Make it yours <svg
                                        viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <path d="M4 10h12m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.6"
                                            stroke-linecap="round" stroke-linejoin="round" />
                                    </svg></a></div>
                        </div>
                    </article>
                    <article class="experience-card">
                        <div class="card-photo">
                            <img src="https://images.unsplash.com/photo-1472396961693-142e6e269027?auto=format&fit=crop&w=1000&q=80"
                                alt="Wildlife roaming in a sunlit green forest" loading="lazy">
                            <span class="card-tag">Forest &amp; primates</span>
                        </div>
                        <div class="card-content">
                            <h3>Into the forest</h3>
                            <p>Trade the open road for ancient green canopies and an unforgettable encounter with
                                nature.</p>
                            <div class="card-bottom"><span>Gorilla trekking · Guided walks</span><a href="#contact"
                                    aria-label="Plan a forest and primate experience">Make it yours <svg
                                        viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <path d="M4 10h12m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.6"
                                            stroke-linecap="round" stroke-linejoin="round" />
                                    </svg></a></div>
                        </div>
                    </article>
                    <article class="experience-card">
                        <div class="card-photo">
                            <img src="https://images.unsplash.com/photo-1518837695005-2083093ee35b?auto=format&fit=crop&w=1000&q=80"
                                alt="A peaceful waterside landscape at golden hour" loading="lazy">
                            <span class="card-tag">People &amp; place</span>
                        </div>
                        <div class="card-content">
                            <h3>Slow things down</h3>
                            <p>Meet local traditions, linger by the water, and leave a little room for the unexpected.
                            </p>
                            <div class="card-bottom"><span>Culture · Lakes · Getaways</span><a href="#contact"
                                    aria-label="Plan a cultural or lakeside getaway">Make it yours <svg
                                        viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <path d="M4 10h12m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.6"
                                            stroke-linecap="round" stroke-linejoin="round" />
                                    </svg></a></div>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section class="quote-band" aria-label="Our travel philosophy">
            <span class="eyebrow">Travel, your way</span>
            <blockquote>“The best journeys aren’t just about where you go. They’re about how it makes you feel.”
            </blockquote>
            <div class="quote-credit">The KIKOMA way</div>
        </section>

        <section class="why">
            <div class="container why-layout">
                <div>
                    <span class="eyebrow">A more personal kind of travel</span>
                    <h2 class="section-heading">Thoughtful from the very first hello.</h2>
                    <p class="section-copy">No off-the-shelf itineraries or one-size-fits-all adventures. Just real
                        conversations, considered details, and room to make the journey your own.</p>
                </div>
                <div class="why-list">
                    <article class="why-item">
                        <span class="why-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none">
                                <path d="M10 17s6-5.1 6-9.5a6 6 0 1 0-12 0C4 11.9 10 17 10 17Z" stroke="currentColor"
                                    stroke-width="1.4" />
                                <circle cx="10" cy="7.5" r="2" stroke="currentColor" stroke-width="1.4" />
                            </svg></span>
                        <h3>Made around you</h3>
                        <p>Your pace, your people, the places you’re dreaming about. We start by listening.</p>
                    </article>
                    <article class="why-item">
                        <span class="why-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none">
                                <path d="M3 14.5 7.2 10l3 2.5L16.5 5M12 5h4.5v4.5" stroke="currentColor"
                                    stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
                            </svg></span>
                        <h3>Local perspective</h3>
                        <p>Discover more than the highlights with thoughtful guidance along the way.</p>
                    </article>
                    <article class="why-item">
                        <span class="why-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none">
                                <path
                                    d="M10 17s-6.5-3.7-6.5-9A3.5 3.5 0 0 1 10 6a3.5 3.5 0 0 1 6.5 2c0 5.3-6.5 9-6.5 9Z"
                                    stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                                <path d="M7.5 10h5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                            </svg></span>
                        <h3>Travel with care</h3>
                        <p>We believe the best trips make a positive connection with the places they touch.</p>
                    </article>
                    <article class="why-item">
                        <span class="why-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none">
                                <circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="1.4" />
                                <path d="M10 6v4l2.5 1.5" stroke="currentColor" stroke-width="1.4"
                                    stroke-linecap="round" />
                            </svg></span>
                        <h3>Room to breathe</h3>
                        <p>A considered pace with time to take it all in, not just tick off a list.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="journey" id="journey">
            <div class="container">
                <span class="eyebrow">A few simple steps</span>
                <h2 class="section-heading">From “what if?” to “that was incredible.”</h2>
                <div class="steps">
                    <article class="step"><span class="step-number">01</span>
                        <h3>Tell us what you’re dreaming of</h3>
                        <p>Share your travel dates, what you love, and any ideas already taking shape.</p>
                    </article>
                    <article class="step"><span class="step-number">02</span>
                        <h3>Shape a journey together</h3>
                        <p>We’ll talk through your plans and explore an itinerary that feels right for you.</p>
                    </article>
                    <article class="step"><span class="step-number">03</span>
                        <h3>Arrive ready to explore</h3>
                        <p>With the details in place, you can focus on the moments you came for.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="contact" id="contact">
            <div class="container contact-panel">
                <div class="contact-copy">
                    <span class="eyebrow">Your next chapter starts here</span>
                    <h2>Let’s make a little room for adventure.</h2>
                    <p>Have a destination in mind, or just the feeling that it’s time to go? Tell us a little about it
                        and start shaping your Uganda journey.</p>
                    <div class="contact-aside"><strong>A note about this form</strong>Your trip request will be saved
                        securely so our team can follow up with you.</div>
                </div>
                <form class="contact-form" id="trip-form" method="post" action="index.php#contact">
                    <div class="form-grid">
                        <div class="field"><label for="traveler-name">Your name</label><input id="traveler-name"
                                name="name" type="text" autocomplete="name" placeholder="How should we address you?"
                                required></div>
                        <div class="field"><label for="traveler-email">Email address</label><input id="traveler-email"
                                name="email" type="email" autocomplete="email" placeholder="you@example.com" required>
                        </div>
                        <div class="field"><label for="travel-date">When are you thinking?</label><input
                                id="travel-date" name="date" type="month"></div>
                        <div class="field"><label for="travel-style">What sounds like you?</label><select
                                id="travel-style" name="style">
                                <option value="">Choose an experience</option>
                                <option>Wildlife safari</option>
                                <option>Gorilla trekking &amp; forest</option>
                                <option>Culture &amp; local experiences</option>
                                <option>Lakeside escape</option>
                                <option>A bit of everything</option>
                                <option>Still figuring it out</option>
                            </select></div>
                        <div class="field field-full"><label for="trip-notes">Anything else you’d like us to
                                know?</label><textarea id="trip-notes" name="notes"
                                placeholder="Travelling with family, a special occasion, places you’re curious about…"></textarea>
                        </div>
                    </div>
                    <button class="button form-submit" type="submit">Prepare my trip request <svg viewBox="0 0 20 20"
                            fill="none" aria-hidden="true">
                            <path d="M4 10h12m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.6"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg></button>
                    <p class="form-note">We’ll use your details to respond to your trip enquiry.</p>
                    <p class="form-status" id="form-status" role="status" aria-live="polite"><?= htmlspecialchars($formStatus, ENT_QUOTES, 'UTF-8') ?></p>
                </form>
            </div>
        </section>

        <section class="faq" id="faq">
            <div class="container faq-layout">
                <div>
                    <span class="eyebrow">Good to know</span>
                    <h2 class="section-heading">A few things you might be wondering.</h2>
                    <p class="section-copy">Every trip is different. If you have a question that isn’t here, include it
                        in your trip request.</p>
                </div>
                <div class="faq-list">
                    <article class="faq-item">
                        <button class="faq-question" type="button" aria-expanded="false"
                            aria-controls="faq-answer-1">Can you tailor a trip to my dates and interests?<svg
                                viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" />
                            </svg></button>
                        <div class="faq-answer" id="faq-answer-1" hidden>That’s the idea. Share your dates, interests,
                            and the kind of experience you’re looking for. We can discuss what might suit your plans.
                        </div>
                    </article>
                    <article class="faq-item">
                        <button class="faq-question" type="button" aria-expanded="false"
                            aria-controls="faq-answer-2">When is a good time to visit Uganda?<svg viewBox="0 0 20 20"
                                fill="none" aria-hidden="true">
                                <path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" />
                            </svg></button>
                        <div class="faq-answer" id="faq-answer-2" hidden>Uganda’s climate varies by region and season.
                            The best timing depends on your route and activities, so mention your preferred dates and we
                            can talk through the options.</div>
                    </article>
                    <article class="faq-item">
                        <button class="faq-question" type="button" aria-expanded="false"
                            aria-controls="faq-answer-3">Can I include gorilla trekking in my trip?<svg
                                viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" />
                            </svg></button>
                        <div class="faq-answer" id="faq-answer-3" hidden>Gorilla trekking is subject to permit
                            availability and current park requirements. Include it in your request and ask about the
                            latest availability before you make travel plans.</div>
                    </article>
                    <article class="faq-item">
                        <button class="faq-question" type="button" aria-expanded="false" aria-controls="faq-answer-4">I
                            don’t have an itinerary yet. Is that okay?<svg viewBox="0 0 20 20" fill="none"
                                aria-hidden="true">
                                <path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" />
                            </svg></button>
                        <div class="faq-answer" id="faq-answer-4" hidden>Absolutely. You can start with a rough idea—or
                            no idea at all. Tell us what you enjoy, how long you might have, and we can take it from
                            there.</div>
                    </article>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-main">
                <a class="brand" href="#home" aria-label="KIKOMA Tour and Travel, back to top">
                    <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32" fill="none">
                            <path d="M6 23.5 15.8 7l9.8 16.5H6Z" stroke="currentColor" stroke-width="1.5"
                                stroke-linejoin="round" />
                            <path d="m11.3 23.5 4.5-7.4 4.5 7.4M4 26h24" stroke="currentColor" stroke-width="1.5"
                                stroke-linecap="round" />
                        </svg></span>
                    <span><span class="brand-name">KIKOMA</span><span class="brand-caption">Tour &amp;
                            Travel</span></span>
                </a>
                <div class="footer-links"><a href="#experiences">Experiences</a><a href="#about">Our approach</a><a
                        href="#journey">How it works</a><a href="#faq">FAQs</a><a href="#contact">Plan your trip</a>
                </div>
            </div>
            <div class="footer-bottom"><span>© <span id="year"></span> KIKOMA Tour &amp; Travel. All rights
                    reserved.</span><span>Find your wild. Leave with a story.</span></div>
        </div>
    </footer>

    <script>
        const menuToggle = document.querySelector(".menu-toggle");
        const navLinks = document.querySelector("#nav-links");

        menuToggle.addEventListener("click", () => {
            const isOpen = menuToggle.getAttribute("aria-expanded") === "true";
            menuToggle.setAttribute("aria-expanded", String(!isOpen));
            menuToggle.setAttribute("aria-label", isOpen ? "Open navigation menu" : "Close navigation menu");
            navLinks.classList.toggle("is-open", !isOpen);
        });

        navLinks.addEventListener("click", (event) => {
            if (event.target.closest("a")) {
                menuToggle.setAttribute("aria-expanded", "false");
                menuToggle.setAttribute("aria-label", "Open navigation menu");
                navLinks.classList.remove("is-open");
            }
        });

        document.querySelectorAll(".faq-question").forEach((button) => {
            button.addEventListener("click", () => {
                const isExpanded = button.getAttribute("aria-expanded") === "true";
                button.setAttribute("aria-expanded", String(!isExpanded));
                document.getElementById(button.getAttribute("aria-controls")).hidden = isExpanded;
            });
        });

        document.querySelector("#year").textContent = new Date().getFullYear();
    </script>
</body>

</html>