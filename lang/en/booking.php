<?php

return [

    'site' => [
        'subtitle' => 'Table reservations',
        'lookup_link' => 'Find my booking',
        'footer' => 'Table reservations',
    ],

    'hero' => [
        'title' => 'Book a table',
        'eyebrow' => 'Reserve before you arrive',
        'hours' => 'Open :open – :close',
        'map' => 'View map',
    ],

    'form' => [
        'branch' => 'Location',
        'step_date' => 'Pick a date',
        'step_party' => 'How many guests?',
        'step_time' => 'Pick a time',
        'step_guest' => 'Your details',

        'today' => 'Today',
        'tomorrow' => 'Tomorrow',
        'other_day' => 'Other date',

        'party_over_max' => 'For parties over :max, please call :phone so we can arrange it for you.',
        'the_venue' => 'the bar',

        'loading_slots' => 'Loading available times…',
        'slot_legend' => 'Times shown struck through are fully booked or outside service hours.',
        'no_service_day' => 'We are not taking bookings on this date.',
        'slots_failed' => 'We could not load the available times. Please try again, or call us directly.',
        'late_note' => 'After :last we stay open until :close, but we no longer take new bookings.',

        'name' => 'Full name',
        'phone' => 'Phone number',
        'email' => 'Email',
        'note' => 'Notes',
        'optional' => '(optional)',
        'email_hint' => 'Add an email and we will confirm by email as well.',
        'note_placeholder' => 'Special occasion, preferred area, any requests…',

        'cta_idle' => 'Pick a date and time',
        'sending' => 'Sending…',
        'cta_lead' => 'Book at least :minutes minutes ahead',
        'guests' => ':count guests',
        'submit' => 'Book a table',

        'noscript' => 'JavaScript is turned off, so we cannot load the available times. Please turn it on, or call :phone to book.',
    ],

    'ticket' => [
        'code' => 'Booking code',
        'pending_note' => 'A member of the :venue team will call to confirm your booking as soon as possible.',
        'venue' => 'Venue',
        'address' => 'Address',
        'time' => 'Time',
        'party' => 'Guests',
        'area' => 'Area',
        'booked_by' => 'Booked by',
        'note' => 'Notes',
        'cancel_reason' => 'Reason for cancelling',
        'change_hint' => 'To change the time or party size, please call :phone.',
        'book_again' => 'Make another booking',
        'thanks_title' => 'We have received your booking request',
        'thanks_body' => 'Save the code below so you can look it up later.',
        'cancelled' => 'Your booking has been cancelled. Thank you for letting us know.',
        'saved_title' => 'Booking confirmation',
        'email_sent' => 'A confirmation email has been sent to :email.',
        'keep_title' => 'Keep your booking code',
        'keep_body' => 'If the confirmation does not reach :email, save this image or look the booking up any time with your code.',
        'no_email_title' => 'You did not leave an email',
        'no_email_body' => 'Take a screenshot of this page, or tap the button below to save the confirmation as an image. You can also look it up any time with your booking code.',
        'save_image' => 'Save confirmation image',
        'saving_image' => 'Creating image…',
        'save_failed' => 'We could not create the image. Please take a screenshot instead.',
        'image_footer' => 'Please show this code when you arrive.',
    ],

    'cancel' => [
        'title' => 'Cancel this booking',
        'hint' => 'Confirm with the phone number you used to book.',
        'reason' => 'Reason',
        'submit' => 'Cancel booking',
        'confirm' => 'Are you sure you want to cancel booking :code?',
    ],

    'lookup' => [
        'eyebrow' => 'Look up',
        'title' => 'Check your booking',
        'intro' => 'Enter the booking code from your confirmation message, along with the phone number you booked with.',
        'code' => 'Booking code',
        'phone' => 'Phone number',
        'submit' => 'Look up',
        'not_found' => 'We could not find a booking matching that code and phone number.',
        'detail' => 'View details',
    ],

    'closed' => [
        'title' => 'Online booking is not open yet',
        'body' => ':venue is not taking bookings through this page yet.',
        'call' => 'Please call :phone to book a table.',
        'contact' => 'Please contact the bar directly.',
        'still_lookup' => 'If you booked earlier, you can still look it up with your booking code.',
    ],

    'status' => [
        'pending' => 'Awaiting confirmation',
        'confirmed' => 'Confirmed',
        'seated' => 'Seated',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No show',
    ],

    'errors' => [
        'no_tables' => 'We are fully booked at this time for :count guests. Please choose another time.',
        'closed' => 'This bar is not taking bookings at the moment.',
        'party_invalid' => 'That is not a valid number of guests.',
        'party_too_big' => 'For parties of :count or more, please call :phone so we can arrange it.',
        'bad_slot' => 'That time does not match our opening hours.',
        'closed_slot' => 'We are closed at that time.',
        'too_soon' => 'Please book at least :minutes minutes ahead. If it is sooner than that, please call us directly.',
        'too_far' => 'We only take bookings up to :days days ahead.',
        'blocked' => 'This phone number cannot book online at the moment. Please call the bar directly.',
        'phone_mismatch' => 'That phone number does not match this booking.',
        'cannot_cancel' => 'This booking cannot be cancelled online. Please call the bar directly.',
        'day_full' => 'We are fully booked on this date. Please try another day.',
        'too_far_days' => 'We only take bookings up to :days days ahead.',
        'party_call' => 'For a large party, please call :phone so we can arrange it.',
        'too_many' => 'That is a lot of booking attempts in a row. Please wait a moment and try again, or call us directly.',
    ],

    'notify' => [
        'subject' => [
            'created' => '[:venue] We received your booking request :code',
            'confirmed' => '[:venue] Booking confirmed :code',
            'cancelled' => '[:venue] Booking cancelled :code',
            'updated' => '[:venue] Booking updated :code',
            'reminder' => '[:venue] Reminder: your booking today',
        ],
        'lead' => [
            'created' => 'Hi :name, we have received your booking request and will confirm shortly.',
            'confirmed' => 'Hi :name, your table is confirmed. See you soon!',
            'cancelled' => 'Hi :name, booking :code has been cancelled.',
            'updated' => 'Hi :name, your booking has been updated. Here are the new details.',
            'reminder' => 'Hi :name, a reminder about your booking today.',
        ],
        'code' => 'Booking code',
        'venue' => 'Venue',
        'address' => 'Address',
        'time' => 'Time',
        'time_value' => ':time on :date',
        'party' => 'Guests',
        'tables' => 'Table',
        'reason' => 'Reason',
        'link_hint' => 'View your booking',
        'call_hint' => 'To make changes, please call :phone.',
        'sms' => [
            'created' => 'Booking :code received - :venue :time :date, :count guests. Awaiting confirmation.',
            'confirmed' => 'CONFIRMED :code - :venue :time :date, :count guests. See you soon!',
            'cancelled' => 'Booking :code has been cancelled. Contact us if you need help.',
            'updated' => 'Booking :code moved to :venue :time :date, :count guests.',
            'reminder' => 'Reminder: booking :code today at :time, :venue. See you soon!',
        ],
    ],

];
