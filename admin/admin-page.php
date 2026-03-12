<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap caswell-admin">
    <h1>Caswell Booking Settings</h1>

    <?php settings_errors(); ?>

    <form method="post" action="options.php">
        <?php settings_fields( 'caswell_settings_group' ); ?>

        <?php $o = get_option( 'caswell_settings', [] ); ?>

        <!-- ── Tab navigation ─────────────────────────────────────────── -->
        <nav class="caswell-tabs">
            <a href="#tab-gcal"         class="caswell-tab active">Google Calendar</a>
            <a href="#tab-sessions"     class="caswell-tab">Sessions</a>
            <a href="#tab-availability" class="caswell-tab">Availability</a>
            <a href="#tab-square"       class="caswell-tab">Square</a>
            <a href="#tab-venmo"    class="caswell-tab">Venmo</a>
            <a href="#tab-email"    class="caswell-tab">Email</a>
            <a href="#tab-sms"      class="caswell-tab">SMS / Twilio</a>
            <a href="#tab-schedule" class="caswell-tab">Scheduling</a>
            <a href="#tab-business" class="caswell-tab">Business Info</a>
            <a href="#tab-bookings" class="caswell-tab">Bookings</a>
            <a href="#tab-tools"    class="caswell-tab">Tools</a>
        </nav>

        <!-- ── Google Calendar ────────────────────────────────────────── -->
        <div id="tab-gcal" class="caswell-tab-content active">
            <h2>Google Calendar — OAuth2 Setup</h2>
            <p class="description">
                <strong>One-time setup:</strong>
                <ol style="list-style:decimal;margin-left:20px;">
                    <li>Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a> → APIs &amp; Services → Credentials → <em>Create Credentials → OAuth 2.0 Client ID</em> (type: Web application). Copy the Client ID and Client Secret.</li>
                    <li>Go to <a href="https://developers.google.com/oauthplayground" target="_blank">OAuth 2.0 Playground</a>. Click the gear icon → check <em>Use your own OAuth credentials</em> → enter your Client ID and Client Secret.</li>
                    <li>In Step 1, enter the scope: <code>https://www.googleapis.com/auth/calendar</code>, click Authorize, and sign in as <strong>caswellrd@gmail.com</strong>.</li>
                    <li>In Step 2, click <em>Exchange authorization code for tokens</em>. Copy the <strong>Refresh token</strong> and paste it below.</li>
                </ol>
            </p>
            <table class="form-table">
                <tr>
                    <th><label for="google_client_id">OAuth2 Client ID</label></th>
                    <td>
                        <input type="text" id="google_client_id" name="caswell_settings[google_client_id]" value="<?php echo esc_attr( $o['google_client_id'] ?? '' ); ?>" class="large-text" />
                    </td>
                </tr>
                <tr>
                    <th><label for="google_client_secret">OAuth2 Client Secret</label></th>
                    <td>
                        <input type="password" id="google_client_secret" name="caswell_settings[google_client_secret]" value="" class="regular-text" placeholder="Leave blank to keep existing" autocomplete="new-password" />
                        <?php if ( ! empty( $o['google_client_secret'] ) ) : ?>
                        <span class="description">✓ Secret saved (encrypted)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="google_refresh_token">OAuth2 Refresh Token</label></th>
                    <td>
                        <input type="password" id="google_refresh_token" name="caswell_settings[google_refresh_token]" value="" class="regular-text" placeholder="Leave blank to keep existing" autocomplete="new-password" />
                        <?php if ( ! empty( $o['google_refresh_token'] ) ) : ?>
                        <span class="description">✓ Refresh token saved (encrypted)</span>
                        <?php endif; ?>
                        <p class="description">Obtained from OAuth Playground (see instructions above). Does not expire as long as the app is active.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="shared_cal_id">Shared Calendar ID</label></th>
                    <td>
                        <input type="text" id="shared_cal_id" name="caswell_settings[shared_calendar_id]" value="<?php echo esc_attr( $o['shared_calendar_id'] ?? '' ); ?>" class="regular-text" />
                        <p class="description">The calendar ID shared by pluckercraft@gmail.com. Find it in Google Calendar → hover over the calendar → three-dot menu → Settings → <em>Calendar ID</em>.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="personal_cal_id">Personal Calendar ID</label></th>
                    <td>
                        <input type="text" id="personal_cal_id" name="caswell_settings[personal_calendar_id]" value="<?php echo esc_attr( $o['personal_calendar_id'] ?? '' ); ?>" class="regular-text" placeholder="primary" />
                        <p class="description">Ryan's own calendar — all events here block availability. Use <code>primary</code> for the main calendar, or the full calendar ID.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="calendar_keyword">Availability Keyword</label></th>
                    <td>
                        <input type="text" id="calendar_keyword" name="caswell_settings[calendar_keyword]" value="<?php echo esc_attr( $o['calendar_keyword'] ?? 'Glow' ); ?>" class="regular-text" />
                        <p class="description">Case-insensitive keyword matched in shared calendar event titles. Default: <code>Glow</code>.</p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ── Session Lengths ────────────────────────────────────────── -->
        <div id="tab-sessions" class="caswell-tab-content">
            <h2>Session Lengths</h2>
            <table class="form-table">
                <tr>
                    <th>Enabled Lengths</th>
                    <td>
                        <?php foreach ( [ 30, 60, 90 ] as $len ) : ?>
                        <label style="margin-right:20px;">
                            <input type="checkbox" name="caswell_settings[enable_<?php echo $len; ?>min]" value="1" <?php checked( ! empty( $o[ "enable_{$len}min" ] ) ); ?> />
                            <?php echo $len; ?> minutes
                        </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="default_length">Default Session Length</label></th>
                    <td>
                        <select id="default_length" name="caswell_settings[default_session_length]">
                            <?php foreach ( [ 30, 60, 90 ] as $len ) : ?>
                            <option value="<?php echo $len; ?>" <?php selected( (int) ( $o['default_session_length'] ?? 60 ), $len ); ?>><?php echo $len; ?> min</option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ── Availability ───────────────────────────────────────────── -->
        <div id="tab-availability" class="caswell-tab-content">
            <h2>Availability</h2>

            <h3>Minimum Advance Booking</h3>
            <table class="form-table">
                <tr>
                    <th><label for="min_hours_advance">Minimum Hours in Advance</label></th>
                    <td>
                        <input type="number" id="min_hours_advance" name="caswell_settings[min_hours_advance]" value="<?php echo esc_attr( $o['min_hours_advance'] ?? 24 ); ?>" min="0" max="720" style="width:80px;" />
                        <p class="description">Clients cannot book within this many hours of the appointment time. Default: 24.</p>
                    </td>
                </tr>
            </table>

            <h3>Weekly Schedule</h3>
            <p class="description">Define Ryan's regular working hours. When a day has a schedule, it is used as the availability window (instead of Glow calendar events). Leave a day disabled to fall back to Glow events for that day.</p>
            <table class="form-table">
                <tr>
                    <th>Day</th>
                    <th>Enabled</th>
                    <th>Start</th>
                    <th>End</th>
                </tr>
                <?php
                $day_names = [ 1=>'Monday', 2=>'Tuesday', 3=>'Wednesday', 4=>'Thursday', 5=>'Friday', 6=>'Saturday', 7=>'Sunday' ];
                $weekly    = $o['weekly_availability'] ?? [];
                foreach ( $day_names as $d => $name ) :
                    $day = $weekly[ $d ] ?? [];
                ?>
                <tr>
                    <td><strong><?php echo esc_html( $name ); ?></strong></td>
                    <td>
                        <input type="checkbox" name="caswell_settings[weekly_availability][<?php echo $d; ?>][enabled]" value="1" <?php checked( ! empty( $day['enabled'] ) ); ?> />
                    </td>
                    <td>
                        <input type="time" name="caswell_settings[weekly_availability][<?php echo $d; ?>][start]" value="<?php echo esc_attr( $day['start'] ?? '09:00' ); ?>" />
                    </td>
                    <td>
                        <input type="time" name="caswell_settings[weekly_availability][<?php echo $d; ?>][end]" value="<?php echo esc_attr( $day['end'] ?? '17:00' ); ?>" />
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>

            <h3>Time Blocks (Vacation / Personal)</h3>
            <p class="description">Block out specific date/time ranges to make them unavailable for booking.</p>

            <?php $blocks = Caswell_Booking_DB::get_all_blocks(); ?>
            <?php if ( $blocks ) : ?>
            <table class="widefat striped" style="max-width:700px;margin-bottom:20px;">
                <thead>
                    <tr><th>Label</th><th>Start</th><th>End</th><th></th></tr>
                </thead>
                <tbody id="caswell-blocks-list">
                    <?php foreach ( $blocks as $block ) : ?>
                    <tr id="caswell-block-row-<?php echo (int) $block->id; ?>">
                        <td><?php echo esc_html( $block->label ?: '—' ); ?></td>
                        <td><?php echo esc_html( wp_date( 'M j, Y g:i A', strtotime( $block->start_datetime ) ) ); ?></td>
                        <td><?php echo esc_html( wp_date( 'M j, Y g:i A', strtotime( $block->end_datetime ) ) ); ?></td>
                        <td><button type="button" class="button button-small caswell-delete-block" data-id="<?php echo (int) $block->id; ?>">Delete</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <p id="caswell-no-blocks">No time blocks defined.</p>
            <tbody id="caswell-blocks-list" style="display:none;"></tbody>
            <?php endif; ?>

            <h4>Add a Time Block</h4>
            <table class="form-table" style="max-width:700px;">
                <tr>
                    <th><label for="new_block_label">Label (optional)</label></th>
                    <td><input type="text" id="new_block_label" placeholder="e.g. Vacation" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="new_block_start">Start</label></th>
                    <td><input type="datetime-local" id="new_block_start" /></td>
                </tr>
                <tr>
                    <th><label for="new_block_end">End</label></th>
                    <td><input type="datetime-local" id="new_block_end" /></td>
                </tr>
            </table>
            <button type="button" id="caswell-add-block" class="button button-primary">Add Block</button>
            <span id="caswell-block-msg" style="margin-left:10px;"></span>
        </div>

        <!-- ── Square ─────────────────────────────────────────────────── -->
        <div id="tab-square" class="caswell-tab-content">
            <h2>Payment — Square</h2>
            <table class="form-table">
                <tr>
                    <th><label for="square_app_id">Application ID</label></th>
                    <td><input type="text" id="square_app_id" name="caswell_settings[square_application_id]" value="<?php echo esc_attr( $o['square_application_id'] ?? '' ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="square_loc_id">Location ID</label></th>
                    <td><input type="text" id="square_loc_id" name="caswell_settings[square_location_id]" value="<?php echo esc_attr( $o['square_location_id'] ?? '' ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="square_token">Access Token</label></th>
                    <td>
                        <input type="password" id="square_token" name="caswell_settings[square_access_token]" value="" class="regular-text" placeholder="Leave blank to keep existing" autocomplete="new-password" />
                        <?php if ( ! empty( $o['square_access_token'] ) ) : ?>
                        <span class="description">✓ Token saved (encrypted)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Sandbox Mode</th>
                    <td>
                        <label><input type="checkbox" name="caswell_settings[square_sandbox_mode]" value="1" <?php checked( ! empty( $o['square_sandbox_mode'] ) ); ?> /> Enable sandbox (test) mode</label>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ── Venmo ──────────────────────────────────────────────────── -->
        <div id="tab-venmo" class="caswell-tab-content">
            <h2>Payment — Venmo</h2>
            <table class="form-table">
                <tr>
                    <th><label for="venmo_user">Venmo Username</label></th>
                    <td><input type="text" id="venmo_user" name="caswell_settings[venmo_username]" value="<?php echo esc_attr( $o['venmo_username'] ?? '' ); ?>" class="regular-text" placeholder="@username" /></td>
                </tr>
                <?php foreach ( [ 30, 60, 90 ] as $len ) : ?>
                <tr>
                    <th><label>Price — <?php echo $len; ?> min</label></th>
                    <td>
                        <span>$</span>
                        <input type="text" name="caswell_settings[venmo_price_<?php echo $len; ?>]" value="<?php echo esc_attr( $o[ "venmo_price_{$len}" ] ?? '' ); ?>" style="width:80px;" placeholder="0.00" />
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- ── Email ──────────────────────────────────────────────────── -->
        <div id="tab-email" class="caswell-tab-content">
            <h2>Notifications — Email</h2>
            <p class="description">Placeholders: <code>{name}</code>, <code>{date}</code>, <code>{time}</code>, <code>{end_time}</code>, <code>{duration}</code>, <code>{timezone}</code>, <code>{site_name}</code>, <code>{site_url}</code></p>
            <table class="form-table">
                <tr>
                    <th><label for="email_from_name">From Name</label></th>
                    <td><input type="text" id="email_from_name" name="caswell_settings[email_from_name]" value="<?php echo esc_attr( $o['email_from_name'] ?? get_bloginfo( 'name' ) ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="email_from_addr">From Email</label></th>
                    <td><input type="email" id="email_from_addr" name="caswell_settings[email_from_address]" value="<?php echo esc_attr( $o['email_from_address'] ?? get_bloginfo( 'admin_email' ) ); ?>" class="regular-text" /></td>
                </tr>
                <tr><th colspan="2"><h3 style="margin:0">Confirmation</h3></th></tr>
                <tr>
                    <th><label for="email_conf_subj">Subject</label></th>
                    <td><input type="text" id="email_conf_subj" name="caswell_settings[email_confirmation_subject]" value="<?php echo esc_attr( $o['email_confirmation_subject'] ?? 'Your appointment is confirmed — {date}' ); ?>" class="large-text" /></td>
                </tr>
                <tr>
                    <th><label for="email_conf_body">Body</label></th>
                    <td><textarea id="email_conf_body" name="caswell_settings[email_confirmation_body]" rows="6" class="large-text"><?php echo esc_textarea( $o['email_confirmation_body'] ?? '' ); ?></textarea></td>
                </tr>
                <tr><th colspan="2"><h3 style="margin:0">Reminder</h3></th></tr>
                <tr>
                    <th><label for="email_rem_subj">Subject</label></th>
                    <td><input type="text" id="email_rem_subj" name="caswell_settings[email_reminder_subject]" value="<?php echo esc_attr( $o['email_reminder_subject'] ?? 'Reminder: Your appointment is tomorrow — {date}' ); ?>" class="large-text" /></td>
                </tr>
                <tr>
                    <th><label for="email_rem_body">Body</label></th>
                    <td><textarea id="email_rem_body" name="caswell_settings[email_reminder_body]" rows="6" class="large-text"><?php echo esc_textarea( $o['email_reminder_body'] ?? '' ); ?></textarea></td>
                </tr>
            </table>
        </div>

        <!-- ── SMS / Twilio ───────────────────────────────────────────── -->
        <div id="tab-sms" class="caswell-tab-content">
            <h2>Notifications — SMS (Twilio)</h2>
            <table class="form-table">
                <tr>
                    <th><label for="twilio_sid">Account SID</label></th>
                    <td><input type="text" id="twilio_sid" name="caswell_settings[twilio_account_sid]" value="<?php echo esc_attr( $o['twilio_account_sid'] ?? '' ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="twilio_token">Auth Token</label></th>
                    <td>
                        <input type="password" id="twilio_token" name="caswell_settings[twilio_auth_token]" value="" class="regular-text" placeholder="Leave blank to keep existing" autocomplete="new-password" />
                        <?php if ( ! empty( $o['twilio_auth_token'] ) ) : ?>
                        <span class="description">✓ Token saved (encrypted)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="twilio_from">From Phone Number</label></th>
                    <td><input type="text" id="twilio_from" name="caswell_settings[twilio_from_phone]" value="<?php echo esc_attr( $o['twilio_from_phone'] ?? '' ); ?>" class="regular-text" placeholder="+15005550006" /></td>
                </tr>
                <tr>
                    <th><label for="sms_conf">Confirmation Template</label></th>
                    <td><textarea id="sms_conf" name="caswell_settings[sms_confirmation_template]" rows="3" class="large-text"><?php echo esc_textarea( $o['sms_confirmation_template'] ?? '' ); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="sms_rem">Reminder Template</label></th>
                    <td><textarea id="sms_rem" name="caswell_settings[sms_reminder_template]" rows="3" class="large-text"><?php echo esc_textarea( $o['sms_reminder_template'] ?? '' ); ?></textarea></td>
                </tr>
                <tr><th colspan="2"><h3 style="margin:0">Owner Notifications</h3></th></tr>
                <tr>
                    <th>Notify Ryan on New Booking</th>
                    <td>
                        <label><input type="checkbox" name="caswell_settings[owner_notify_sms]" value="1" <?php checked( ! empty( $o['owner_notify_sms'] ) ); ?> /> Send SMS to Ryan when a new booking is made</label>
                    </td>
                </tr>
                <tr>
                    <th><label for="owner_notify_phone">Ryan's Phone Number</label></th>
                    <td>
                        <input type="text" id="owner_notify_phone" name="caswell_settings[owner_notify_phone]" value="<?php echo esc_attr( $o['owner_notify_phone'] ?? '' ); ?>" class="regular-text" placeholder="+15005550006" />
                        <p class="description">Phone number to receive new-booking SMS alerts (uses Twilio credentials above).</p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ── Scheduling ─────────────────────────────────────────────── -->
        <div id="tab-schedule" class="caswell-tab-content">
            <h2>Scheduling</h2>
            <table class="form-table">
                <tr>
                    <th><label for="buffer_time">Buffer Between Appointments (min)</label></th>
                    <td><input type="number" id="buffer_time" name="caswell_settings[buffer_time]" value="<?php echo esc_attr( $o['buffer_time'] ?? 15 ); ?>" min="0" max="60" style="width:80px;" /></td>
                </tr>
                <tr>
                    <th><label for="reminder_hours">Reminder Timing (hours before)</label></th>
                    <td><input type="number" id="reminder_hours" name="caswell_settings[reminder_hours_before]" value="<?php echo esc_attr( $o['reminder_hours_before'] ?? 24 ); ?>" min="1" max="168" style="width:80px;" /></td>
                </tr>
                <tr>
                    <th>Reminder Types</th>
                    <td>
                        <label style="margin-right:20px;"><input type="checkbox" name="caswell_settings[enable_email_reminder]" value="1" <?php checked( ! empty( $o['enable_email_reminder'] ) ); ?> /> Send email reminders</label>
                        <label><input type="checkbox" name="caswell_settings[enable_sms_reminder]" value="1" <?php checked( ! empty( $o['enable_sms_reminder'] ) ); ?> /> Send SMS reminders</label>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ── Business Info ──────────────────────────────────────────── -->
        <div id="tab-business" class="caswell-tab-content">
            <h2>Business / Homepage Info</h2>
            <table class="form-table">
                <tr>
                    <th><label for="hero_tagline">Hero Tagline</label></th>
                    <td><input type="text" id="hero_tagline" name="caswell_settings[hero_tagline]" value="<?php echo esc_attr( $o['hero_tagline'] ?? 'Therapeutic Massage for Body & Mind' ); ?>" class="large-text" /></td>
                </tr>
                <tr>
                    <th><label for="ryan_bio">Ryan's Bio</label></th>
                    <td><textarea id="ryan_bio" name="caswell_settings[ryan_bio]" rows="5" class="large-text"><?php echo esc_textarea( $o['ryan_bio'] ?? '' ); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="ryan_creds">Credentials / License</label></th>
                    <td><input type="text" id="ryan_creds" name="caswell_settings[ryan_credentials]" value="<?php echo esc_attr( $o['ryan_credentials'] ?? '' ); ?>" class="regular-text" placeholder="LMT, Licensed Massage Therapist" /></td>
                </tr>
                <tr>
                    <th><label for="biz_phone">Business Phone</label></th>
                    <td><input type="text" id="biz_phone" name="caswell_settings[business_phone]" value="<?php echo esc_attr( $o['business_phone'] ?? '' ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="biz_email">Business Email</label></th>
                    <td><input type="email" id="biz_email" name="caswell_settings[business_email]" value="<?php echo esc_attr( $o['business_email'] ?? '' ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="biz_addr">Address</label></th>
                    <td><textarea id="biz_addr" name="caswell_settings[business_address]" rows="3" class="large-text"><?php echo esc_textarea( $o['business_address'] ?? '' ); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="biz_hours">Hours</label></th>
                    <td><textarea id="biz_hours" name="caswell_settings[business_hours]" rows="4" class="large-text" placeholder="Mon–Fri: 9am–6pm&#10;Sat: 9am–3pm"><?php echo esc_textarea( $o['business_hours'] ?? '' ); ?></textarea></td>
                </tr>
                <tr><th colspan="2"><h3>Service Pricing</h3></th></tr>
                <?php foreach ( [ 30, 60, 90 ] as $len ) : ?>
                <tr>
                    <th><?php echo $len; ?>-min Session</th>
                    <td>
                        Price: $<input type="text" name="caswell_settings[service_price_<?php echo $len; ?>]" value="<?php echo esc_attr( $o[ "service_price_{$len}" ] ?? '' ); ?>" style="width:80px;" placeholder="0" />
                        &nbsp; Description: <input type="text" name="caswell_settings[service_description_<?php echo $len; ?>]" value="<?php echo esc_attr( $o[ "service_description_{$len}" ] ?? '' ); ?>" class="regular-text" />
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- ── Bookings ────────────────────────────────────────────────── -->
        <div id="tab-bookings" class="caswell-tab-content">
            <h2>Recent Bookings</h2>
            <?php
            global $wpdb;
            $recent = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}caswell_bookings ORDER BY created_at DESC LIMIT 50"
            );
            if ( $recent ) : ?>
            <table class="widefat striped" style="margin-bottom:20px;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Date/Time</th>
                        <th>Length</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Notes</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $recent as $b ) : ?>
                    <tr>
                        <td><?php echo (int) $b->id; ?></td>
                        <td><?php echo esc_html( $b->name ); ?></td>
                        <td><?php echo esc_html( $b->email ); ?></td>
                        <td>
                            <?php echo esc_html( wp_date( 'M j, Y', strtotime( $b->start_datetime ) ) ); ?><br>
                            <small><?php echo esc_html( wp_date( 'g:i A', strtotime( $b->start_datetime ) ) ); ?> – <?php echo esc_html( wp_date( 'g:i A', strtotime( $b->end_datetime ) ) ); ?></small>
                        </td>
                        <td><?php echo (int) $b->session_length; ?> min</td>
                        <td><span style="color:<?php echo $b->status === 'confirmed' ? '#27ae60' : ( $b->status === 'cancelled' ? '#c0392b' : '#e67e22' ); ?>;font-weight:600;"><?php echo esc_html( ucfirst( $b->status ) ); ?></span></td>
                        <td><?php echo esc_html( ucfirst( $b->payment_method ) ); ?> — <?php echo esc_html( ucfirst( $b->payment_status ) ); ?></td>
                        <td><?php echo esc_html( wp_trim_words( $b->notes ?? '', 10 ) ); ?></td>
                        <td><small><?php echo esc_html( wp_date( 'M j, g:i A', strtotime( $b->created_at ) ) ); ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="description">Showing most recent 50 bookings.</p>
            <?php else : ?>
            <p>No bookings yet.</p>
            <?php endif; ?>
        </div>

        <!-- ── Tools ───────────────────────────────────────────────────── -->
        <div id="tab-tools" class="caswell-tab-content">
            <h2>Tools &amp; Diagnostics</h2>

            <h3>Debug Logging</h3>
            <p class="description">When enabled, the plugin logs API calls, errors, and booking events to <code>wp-content/caswell-booking.log</code>.</p>
            <?php $logging_enabled = get_option( 'caswell_enable_logging' ); ?>
            <p>
                <label>
                    <input type="checkbox" name="caswell_enable_logging" value="1"
                        <?php checked( $logging_enabled ); ?>
                        onchange="jQuery.post(ajaxurl, { action: 'caswell_toggle_logging', nonce: caswellAdminData.nonce, enabled: this.checked ? 1 : 0 }, function(r){ if(r.success) location.reload(); });" />
                    Enable debug logging
                </label>
            </p>

            <?php
            $log_file = WP_CONTENT_DIR . '/caswell-booking.log';
            if ( file_exists( $log_file ) ) :
                $log_size = filesize( $log_file );
                $log_tail = '';
                if ( $log_size > 0 ) {
                    $lines = file( $log_file );
                    $log_tail = implode( '', array_slice( $lines, -30 ) );
                }
            ?>
            <h3>Recent Log Entries</h3>
            <p class="description">Log file: <code><?php echo esc_html( $log_file ); ?></code> (<?php echo esc_html( size_format( $log_size ) ); ?>)</p>
            <textarea readonly rows="12" class="large-text" style="font-family:monospace;font-size:12px;background:#f8f8f8;"><?php echo esc_textarea( $log_tail ); ?></textarea>
            <?php else : ?>
            <p class="description">No log file exists yet. Enable logging and trigger an action to start logging.</p>
            <?php endif; ?>

            <h3>System Info</h3>
            <table class="form-table">
                <tr>
                    <th>Plugin Version</th>
                    <td><code><?php echo esc_html( CASWELL_VERSION ); ?></code></td>
                </tr>
                <tr>
                    <th>DB Version</th>
                    <td><code><?php echo esc_html( get_option( 'caswell_db_version', '?' ) ); ?></code></td>
                </tr>
                <tr>
                    <th>WordPress Timezone</th>
                    <td><code><?php echo esc_html( wp_timezone_string() ); ?></code></td>
                </tr>
                <tr>
                    <th>Google Token Cached</th>
                    <td><?php echo get_transient( 'caswell_google_token' ) ? 'Yes' : 'No'; ?></td>
                </tr>
                <tr>
                    <th>Next Reminder Cron</th>
                    <td>
                        <?php
                        $next = wp_next_scheduled( Caswell_Cron::HOOK_REMINDERS );
                        echo $next ? esc_html( wp_date( 'Y-m-d H:i:s', $next ) ) : '<span style="color:red;">Not scheduled</span>';
                        ?>
                    </td>
                </tr>
                <tr>
                    <th>SSL Active</th>
                    <td><?php echo is_ssl() ? 'Yes' : '<span style="color:red;">No — required for Square payments</span>'; ?></td>
                </tr>
            </table>
        </div>

        <?php submit_button( 'Save Settings' ); ?>
    </form>
</div>

<style>
.caswell-tabs { display:flex; gap:4px; border-bottom:2px solid #2271b1; margin-bottom:20px; flex-wrap:wrap; }
.caswell-tab { padding:8px 16px; background:#f0f0f1; border:1px solid #c3c4c7; border-bottom:none; text-decoration:none; color:#1d2327; border-radius:4px 4px 0 0; }
.caswell-tab.active { background:#fff; border-bottom-color:#fff; font-weight:600; color:#2271b1; }
.caswell-tab-content { display:none; }
.caswell-tab-content.active { display:block; }
</style>

<script>
(function(){
    var tabs = document.querySelectorAll('.caswell-tab');
    var contents = document.querySelectorAll('.caswell-tab-content');
    tabs.forEach(function(tab){
        tab.addEventListener('click', function(e){
            e.preventDefault();
            var target = this.getAttribute('href').replace('#','');
            tabs.forEach(function(t){ t.classList.remove('active'); });
            contents.forEach(function(c){ c.classList.remove('active'); });
            this.classList.add('active');
            document.getElementById(target).classList.add('active');
        });
    });
})();

// Time blocks AJAX
(function($){
    function formatDt(val) {
        // datetime-local gives "YYYY-MM-DDTHH:MM" — convert to MySQL datetime
        return val.replace('T', ' ') + ':00';
    }

    $('#caswell-add-block').on('click', function(){
        var label = $('#new_block_label').val();
        var start = $('#new_block_start').val();
        var end   = $('#new_block_end').val();
        var $msg  = $('#caswell-block-msg');

        if (!start || !end) { $msg.text('Start and end are required.'); return; }

        $.post(caswellAdminData.ajax_url, {
            action: 'caswell_add_block',
            nonce:  caswellAdminData.nonce,
            label:  label,
            start_datetime: formatDt(start),
            end_datetime:   formatDt(end)
        }, function(res) {
            if (!res.success) { $msg.text(res.data || 'Error.'); return; }
            var id = res.data.id;
            // Build label display
            var labelText = label || '—';
            var startLabel = start.replace('T', ' ');
            var endLabel   = end.replace('T', ' ');
            var row = '<tr id="caswell-block-row-' + id + '">' +
                '<td>' + $('<span>').text(labelText).html() + '</td>' +
                '<td>' + startLabel + '</td>' +
                '<td>' + endLabel + '</td>' +
                '<td><button type="button" class="button button-small caswell-delete-block" data-id="' + id + '">Delete</button></td>' +
                '</tr>';
            if ($('#caswell-blocks-list').length) {
                $('#caswell-blocks-list').append(row);
            }
            $('#caswell-no-blocks').hide();
            $('#new_block_label, #new_block_start, #new_block_end').val('');
            $msg.text('Block added.');
        });
    });

    $(document).on('click', '.caswell-delete-block', function(){
        var id  = $(this).data('id');
        var $row = $('#caswell-block-row-' + id);
        if (!confirm('Delete this time block?')) return;
        $.post(caswellAdminData.ajax_url, {
            action:   'caswell_delete_block',
            nonce:    caswellAdminData.nonce,
            block_id: id
        }, function(res) {
            if (res.success) { $row.remove(); }
        });
    });
}(jQuery));
</script>
