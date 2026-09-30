<?php

// Customer website strings (Phase 6). Business-rule messages come from messages.php, shared with the API.
return [
    'nav' => ['search' => 'Search', 'my_bookings' => 'My bookings', 'wallet' => 'Wallet', 'profile' => 'Profile', 'login' => 'Sign in', 'register' => 'Create account', 'logout' => 'Sign out', 'language' => 'Language'],
    'common' => ['loading' => 'Loading…', 'back' => 'Back', 'optional' => 'optional', 'times_note' => 'All times are shown in :tz.'],
    'search' => [
        'title' => 'Find a bike or car', 'country' => 'Country', 'city' => 'City', 'any' => 'Any', 'pick_up' => 'Pick-up', 'return' => 'Return',
        'category' => 'Category', 'type' => 'Type', 'bike' => 'Bike', 'car' => 'Car', 'seats' => 'Min. seats', 'button' => 'Search',
        'no_results' => 'No vehicles match your search. Try other dates or filters.', 'dates_invalid' => 'Return must be after pick-up.',
        'total_for_dates' => 'total for your dates', 'pick_dates_for_price' => 'Choose pick-up and return to see the price.',
    ],
    'vehicle' => [
        'seats' => ':n seat|:n seats', 'seats_label' => 'Seats', 'transmission' => 'Transmission', 'fuel' => 'Fuel', 'branch' => 'Branch',
        'available' => 'Available for these dates', 'unavailable' => 'Not available for these dates', 'addons' => 'Add-ons',
        'addon_per_day' => 'per day', 'addon_per_booking' => 'per booking', 'qty' => 'Quantity', 'coupon' => 'Coupon code', 'quote' => 'Your price',
        'subtotal' => 'Subtotal', 'discounts' => 'Discounts', 'tax' => 'Tax', 'total' => 'Total', 'deposit' => 'Refundable deposit', 'due_now' => 'To pay now',
        'book_now' => 'Book now', 'login_to_book' => 'Sign in to book', 'price_unavailable' => 'No price for these dates',
    ],
    'auth' => [
        'sign_in' => 'Sign in', 'with_password' => 'Password', 'with_otp' => 'Phone code', 'email' => 'Email', 'password' => 'Password',
        'password_confirmation' => 'Confirm password', 'name' => 'Full name', 'phone' => 'Phone', 'phone_hint' => 'International format, e.g. +919900000000',
        'code' => '6-digit code', 'send_code' => 'Send code', 'verify' => 'Verify and sign in', 'no_account' => 'New here?',
        'have_account' => 'Already have an account?', 'create_account' => 'Create account', 'too_many' => 'Too many attempts. Please wait a minute and try again.',
    ],
    'checkout' => [
        'title' => 'Checkout', 'pay' => 'Pay', 'pay_wallet' => 'Pay with wallet', 'pay_stripe' => 'Pay by card (Stripe)', 'pay_razorpay' => 'Pay with Razorpay',
        'no_card' => 'Card payments are not available right now.', 'hold_note' => 'We hold this vehicle for you until :time.',
    ],
    'confirmation' => [
        'title' => 'Booking status', 'confirmed' => 'Your booking is confirmed.', 'waiting' => 'Waiting for payment confirmation. This page updates automatically.',
        'failed' => 'This booking was cancelled. Any money received has been returned to your wallet.', 'card_failed' => 'The card payment did not go through. You can try again.',
        'view_booking' => 'View booking',
    ],
    'booking' => [
        'title' => 'Booking #:id', 'none' => 'You have no bookings yet.', 'amount_due' => 'Amount due', 'amount_paid' => 'Paid', 'pay_balance' => 'Pay now',
        'new_end' => 'New return time', 'extend' => 'Extend booking', 'cancel' => 'Cancel booking', 'cancel_confirm' => 'Cancel this booking? Everything you paid goes back to your wallet.',
        'invoice' => 'Download invoice (PDF)', 'deposit_status' => 'Deposit', 'cancelled_notice' => 'Booking cancelled. The refund is in your wallet.',
        'extended_notice' => 'Booking extended.',
    ],
    'deposit' => ['held' => 'held', 'released' => 'released'],
    'status' => [
        'draft' => 'Awaiting payment', 'pending_payment' => 'Payment in progress', 'confirmed' => 'Confirmed', 'picked_up' => 'On rent', 'returned' => 'Returned',
        'completed' => 'Completed', 'settled' => 'Closed', 'cancelled' => 'Cancelled', 'no_show' => 'No-show',
    ],
    'wallet' => [
        'balance' => 'Balance', 'empty' => 'Your wallet is empty.', 'no_transactions' => 'No transactions yet.', 'top_up' => 'Top up', 'amount' => 'Amount',
        'currency' => 'Currency', 'date' => 'Date', 'type' => 'Type', 'processing' => 'Complete the payment in the window that opened. Your balance updates once the payment provider confirms it.',
        'amount_invalid' => 'Enter a valid amount.', 'type_topup' => 'Top-up', 'type_payment' => 'Booking payment', 'type_refund' => 'Refund', 'type_deposit_release' => 'Deposit returned',
    ],
    'profile' => ['save' => 'Save', 'saved' => 'Profile saved.'],
];
