<?php
    require_once __DIR__ . "/partials/token_guard.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upgrade Plan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .success-checkmark {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #22c55e;
            color: white;
            font-size: 36px;
            line-height: 64px;
            margin: 0 auto;
        }
        .failed-checkmark {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #bd262e;
            color: white;
            font-size: 36px;
            line-height: 64px;
            margin: 0 auto;
        }

        .brand-preview {
            width: 240px;
            height: 90px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .brand-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
    </style>
</head>

<body class="bg-light">
<?php include "partials/navbar.php"; ?>

<div class="container-fluid">
    <div class="row">
        <?php include "partials/sidebar.php"; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-5 py-5">
            <div class="d-flex justify-content-center">
                <div class="w-100" style="max-width: 900px;">
                    <div class="mb-5 text-center">
                        <h2 class="fw-semibold">Upgrade your posting experience</h2>
                        <p class="text-muted mt-2">
                            Simple pricing. No subscriptions. Pay once and use anytime.
                        </p>
                    </div>
                    <div class="row g-4 justify-content-center">
                        <div class="col-md-6">
                            <div class="card h-100 shadow-sm border-dark">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title">Basic</h5>
                                    <p class="text-muted">For occasional posting</p>
                                    <h3 class="fw-bold mt-3">₹199</h3>
                                    <p class="text-muted small">One-time payment · GST included</p>
                                    <hr>
                                    <ul class="list-unstyled mt-3 mb-4">
                                        <li class="mb-2">✔ 20 post credits</li>
                                        <li class="mb-2">✔ Credits never expire</li>
                                        <li class="mb-2">✔ No subscription required</li>
                                        <li class="mb-2">✔ Full posting access</li>
                                        <li class="mb-2">✔ Instant activation</li>
                                    </ul>
                                    <button class="btn btn-dark w-100 mt-auto" onclick="buyCredits(20)">Buy Basic</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100 shadow-sm border-dark">
                                <div class="card-body d-flex flex-column">
                                    <span class="badge bg-dark align-self-start mb-2">Recommended</span>
                                    <h5 class="card-title mt-2">Pro</h5>
                                    <p class="text-muted">Best for active users</p>
                                    <h3 class="fw-bold mt-3">₹399</h3>
                                    <p class="text-muted small">One-time payment · GST included</p>
                                    <hr>
                                    <ul class="list-unstyled mt-3 mb-4">
                                        <li class="mb-2">✔ 60 post credits</li>
                                        <li class="mb-2">✔ Lowest cost per post</li>
                                        <li class="mb-2">✔ Credits never expire</li>
                                        <li class="mb-2">✔ Priority processing</li>
                                        <li class="mb-2">✔ Ideal for regular posting</li>
                                    </ul>
                                    <button class="btn btn-dark w-100 mt-auto" onclick="buyCredits(60)">Upgrade to Pro</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="text-muted small mt-5 text-center">Payments are processed securely. Credits are added instantly after successful payment.</div>
                </div>
            </div>
        </main>
    </div>

<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content p-4">
            <div class="modal-header border-0">
                <h5 class="modal-title">Complete your payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <div class="brand-preview mx-auto">
                        <img id="brandPreviewImg" src="assets/demo.svg" alt="Supported cards">
                    </div>
                </div>

                <div class="mb-4 text-center">
                    <p class="fw-semibold mt-3">
                        Amount: ₹<span id="payAmount"></span>
                    </p>
                </div>
                <form id="payment-form">
                    <div class="mb-3">
                        <label class="form-label">Card Number</label>
                        <div id="card-number" class="form-control"></div>
                        <small id="card-error" class="text-danger small"></small>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Expiry</label>
                            <div id="card-expiry" class="form-control"></div>
                        </div>

                        <div class="col-6 mb-3">
                            <label class="form-label">CVC</label>
                            <div id="card-cvc" class="form-control"></div>
                        </div>
                    </div>
                    <button type="button" id="payBtn"
                        class="btn btn-dark w-100 d-flex align-items-center justify-content-center">
                        <span id="payBtnText">
                            Pay ₹<span id="payBtnAmount"></span>
                        </span>
                        <span id="paySpinner" class="spinner-border spinner-border-sm ms-2 d-none"></span>
                    </button>
                </form>

                <div id="paymentSuccess" class="text-center d-none">
                    <div class="mb-3">
                        <div class="success-checkmark">✓</div>
                    </div>
                    <div class="alert alert-success text-center text-success">
                        <h5>Payment successful</h5>
                        <span class="mt-2">
                            Redirecting to dashboard in <span id="redirect-timer">5</span> seconds…
                        </span>
                    </div>
                </div>
                <div id="paymentPending" class="text-center d-none">
                    <div class="mb-3">
                        <div class="success-checkmark">…</div>
                    </div>
                    <div class="alert alert-warning text-center text-warning">
                        <h6>Payment is processing. We will update your credits shortly.</h6>
                    </div>
                </div>
                <div id="paymentError" class="text-center d-none">
                    <div class="mb-3">
                        <div class="failed-checkmark">Ｘ</div>
                    </div>
                    <div class="alert alert-danger text-center text-danger">
                        <h6>Payment Failed due to: <span id="failed-msg"></span> Please retry</h6>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://js.stripe.com/v3/"></script>
<script>
    const stripe = Stripe("<?= $_ENV['STRIPE_PUBLISH_KEY'] ?>");
    const elements = stripe.elements();
    let selectedCredits = 0;
    let selectedAmount = 0;
    const brandLogos = {
        demo: 'assets/demo.svg',
        visa: 'assets/visa.svg',
        mastercard: 'assets/mastercard.svg',
        amex: 'assets/amex.svg',
        discover: 'assets/discover.svg',
        diners: 'assets/diners.svg',
        jcb: 'assets/jcb.svg',
        unionpay: 'assets/unionpay.svg',
        maestro: 'assets/maestro.svg',
        elo: 'assets/elo.svg',
        mir: 'assets/mir.svg',
        hipercard: 'assets/hipercard.svg',
        hiper: 'assets/hiper.svg',
        rupay: 'assets/rupay.svg'
    };

    const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
    const style = {
        base: {
            fontSize: '16px',
            color: '#212529',
            fontFamily: 'system-ui, -apple-system, BlinkMacSystemFont',
            '::placeholder': { color: '#6c757d' }
        },
        invalid: {
            color: '#dc3545'
        }
    };

    const cardNumber = elements.create('cardNumber', { style });
    const cardExpiry = elements.create('cardExpiry', { style });
    const cardCvc = elements.create('cardCvc', { style });

    cardNumber.mount('#card-number');
    cardExpiry.mount('#card-expiry');
    cardCvc.mount('#card-cvc');

    function setBrandPreview(brand){
        const img = document.getElementById('brandPreviewImg');
        if(!img) return;

        const key = (brand && brand !== 'unknown') ? brand : 'demo';
        img.src = brandLogos[key] || brandLogos.demo;
        img.alt = key;
    }

    function buyCredits(credits) {
        selectedCredits = credits;
        selectedAmount = credits === 20 ? 199 : 399;
        document.getElementById("payAmount").innerText = selectedAmount;
        document.getElementById("payBtnAmount").innerText = selectedAmount;
        setBrandPreview('demo');
        document.getElementById('card-error').textContent = '';
        document.getElementById("paymentSuccess").classList.add("d-none");
        document.getElementById("paymentPending").classList.add("d-none");
        document.getElementById("paymentError").classList.add("d-none");
        document.getElementById("payment-form").classList.remove("d-none");
        modal.show();
    }

    function setPayLoading(isLoading) {
        const btn = document.getElementById("payBtn");
        const spinner = document.getElementById("paySpinner");
        const text = document.getElementById("payBtnText");
        if(isLoading){
            btn.disabled = true;
            spinner.classList.remove("d-none");
            text.textContent = "Processing…";
        }else{
            btn.disabled = false;
            spinner.classList.add("d-none");
            text.innerHTML = `Pay ₹<span id="payBtnAmount">${selectedAmount}</span>`;
        }
    }

    function showPaymentSuccess(){
        document.getElementById("payment-form").classList.add("d-none");
        document.getElementById("paymentSuccess").classList.remove("d-none");
        document.getElementById("paymentPending").classList.add("d-none");
        document.getElementById("paymentError").classList.add("d-none");
        
        let seconds = 5;
        const timerEl = document.getElementById("redirect-timer");
        timerEl.textContent = seconds;

        const interval = setInterval(() => {
            seconds--;
            timerEl.textContent = seconds;

            if (seconds <= 0) {
                clearInterval(interval);
                window.location.href = 'user_dashboard.php';
            }
        }, 1000);
    }

    function showPaymentPending(){
        document.getElementById("payment-form").classList.add("d-none");
        document.getElementById("paymentSuccess").classList.add("d-none");
        document.getElementById("paymentError").classList.add("d-none");
        document.getElementById("paymentPending").classList.remove("d-none");
    }

    function showPaymentError(message){
        document.getElementById("payment-form").classList.add("d-none");
        document.getElementById("paymentSuccess").classList.add("d-none");
        document.getElementById("paymentPending").classList.add("d-none");
        const msgEl = document.getElementById("failed-msg");
        msgEl.textContent = message;
        document.getElementById("paymentError").classList.remove("d-none");
    }

    cardNumber.on('change', function (event) {
        const errorDiv = document.getElementById('card-error');
        errorDiv.textContent = event.error ? event.error.message : '';

        if(event.empty){
            setBrandPreview('demo');
            return;
        }
        setBrandPreview(event.brand);
    });


    document.getElementById("payBtn").addEventListener("click", async () => {
        try{
            setPayLoading(true);
            const res = await fetch("api/payments/create_payment_intent.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ credits: selectedCredits })
            });

            const data = await res.json();
            if (!data.success) throw new Error(data.message);

            const result = await stripe.confirmCardPayment(
                data.client_secret,
                { payment_method: { card: cardNumber } }
            );

            if (result.error){
                throw new Error(result.error.message || "Payment Failed");
            }
            
            const status = result.paymentIntent?.status;
            if (status === "processing") {
                showPaymentPending();
                setPayLoading(false);
                return;
            }

            if (status !== "succeeded") {
                throw new Error("Payment not completed. Please try again.");
            }

            showPaymentSuccess();
            setPayLoading(false);

        } catch (err) {
            setPayLoading(false);
            showPaymentError(err.message);
        }
    });
</script>
</body>
</html>
