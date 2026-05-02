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
                    <li>In Step 1, enter the scope: <code>https://www.googleapis.com/auth/calendar</code>, click Authorize, and sign in with the Google account that owns your calendars.</li>
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
                        <p class="description">The shared calendar ID that contains your availability events. Find it in Google Calendar → hover over the calendar → three-dot menu → Settings → <em>Calendar ID</em>.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="personal_cal_id">Personal Calendar ID</label></th>
                    <td>
                        <input type="text" id="personal_cal_id" name="caswell_settings[personal_calendar_id]" value="<?php echo esc_attr( $o['personal_calendar_id'] ?? '' ); ?>" class="regular-text" placeholder="primary" />
                        <p class="description">Your personal calendar — all events here block availability. Use <code>primary</code> for the main calendar, or the full calendar ID.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="calendar_keyword">Availability Keyword</label></th>
                    <td>
                        <input type="text" id="calendar_keyword" name="caswell_settings[calendar_keyword]" value="<?php echo esc_attr( $o['calendar_keyword'] ?? 'Glow' ); ?>" class="regular-text" />
                        <p class="description">Case-insensitive keyword matched in shared calendar event titles. Default: <code>Glow</code>.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="blocking_keyword">Blocking Keyword</label></th>
                    <td>
                        <input type="text" id="blocking_keyword" name="caswell_settings[blocking_keyword]" value="<?php echo esc_attr( $o['blocking_keyword'] ?? 'Terry' ); ?>" class="regular-text" />
                        <p class="description">Case-insensitive keyword matched in shared calendar event titles or descriptions. Matching events <strong>block</strong> availability (treated like personal-calendar busy). Default: <code>Terry</code>. Leave blank to disable.</p>
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

            <h3>Default Availability</h3>
            <table class="form-table">
                <tr>
                    <th>Open by default</th>
                    <td>
                        <?php $default_avail_on = ! isset( $o['default_avail_open'] ) || ! empty( $o['default_avail_open'] ); ?>
                        <label>
                            <input type="checkbox" name="caswell_settings[default_avail_open]" value="1" <?php checked( $default_avail_on ); ?> />
                            Allow bookings any time within working hours, even when no Glow event is set
                        </label>
                        <p class="description">When on (default), days are open during the working hours below; Glow events on the shared calendar extend availability outside those hours. When off, only Glow events define when bookings are open.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="default_open_start">Working Hours Start</label></th>
                    <td>
                        <input type="time" id="default_open_start" name="caswell_settings[default_open_start]" value="<?php echo esc_attr( $o['default_open_start'] ?? '07:00' ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="default_open_end">Working Hours End</label></th>
                    <td>
                        <input type="time" id="default_open_end" name="caswell_settings[default_open_end]" value="<?php echo esc_attr( $o['default_open_end'] ?? '22:00' ); ?>" />
                    </td>
                </tr>
            </table>

            <h3>Weekly Schedule</h3>
            <p class="description"><strong>Advanced:</strong> use only if you want to restrict a specific day's hours. When a day is enabled here, it overrides both the default open hours above and any Glow events on that day. Leave all days disabled if you want the default-availability rules to apply.</p>
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
                        <td><?php echo esc_html( wp_date( 'M j, Y g:i A', caswell_local_ts($block->start_datetime) ) ); ?></td>
                        <td><?php echo esc_html( wp_date( 'M j, Y g:i A', caswell_local_ts($block->end_datetime) ) ); ?></td>
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
                    <th><label for="notification_channel">Send messages via</label></th>
                    <td>
                        <select id="notification_channel" name="caswell_settings[notification_channel]">
                            <?php $ch = $o['notification_channel'] ?? 'sms'; ?>
                            <option value="sms"      <?php selected( $ch, 'sms' ); ?>>SMS / text message (default)</option>
                            <option value="whatsapp" <?php selected( $ch, 'whatsapp' ); ?>>WhatsApp (via Twilio)</option>
                            <option value="off"      <?php selected( $ch, 'off' ); ?>>Off — email-only</option>
                        </select>
                        <p class="description">
                            <strong>WhatsApp:</strong> requires a Twilio account with an approved WhatsApp sender. For testing, Twilio offers a free sandbox at <a href="https://www.twilio.com/console/sms/whatsapp/sandbox" target="_blank">twilio.com/console/sms/whatsapp/sandbox</a> — clients must opt in by texting a join code to the sandbox number. For production, your Twilio account needs a registered WhatsApp Business sender and approved templates for confirmations/reminders. <strong>Square does not offer general-purpose SMS</strong> (only payment-related receipts), so it isn't an option here.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="twilio_whatsapp_from">WhatsApp Sender</label></th>
                    <td>
                        <input type="text" id="twilio_whatsapp_from" name="caswell_settings[twilio_whatsapp_from]" value="<?php echo esc_attr( $o['twilio_whatsapp_from'] ?? '' ); ?>" class="regular-text" placeholder="+14155238886" />
                        <p class="description">Only used when channel is set to WhatsApp. Twilio sandbox number is <code>+14155238886</code>. Enter the bare phone number — the plugin adds the <code>whatsapp:</code> prefix automatically.</p>
                    </td>
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
                    <th>Notify Owner on New Booking</th>
                    <td>
                        <label><input type="checkbox" name="caswell_settings[owner_notify_sms]" value="1" <?php checked( ! empty( $o['owner_notify_sms'] ) ); ?> /> Send SMS to owner when a new booking is made</label>
                    </td>
                </tr>
                <tr>
                    <th><label for="owner_notify_phone">Owner Phone Number</label></th>
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

            <h3>Branding &amp; White-Label</h3>
            <p class="description">These fields control how your business appears throughout the site, emails, SMS, and calendar events. Leave blank to use defaults.</p>
            <table class="form-table">
                <tr>
                    <th><label for="business_name">Business Name</label></th>
                    <td>
                        <input type="text" id="business_name" name="caswell_settings[business_name]" value="<?php echo esc_attr( $o['business_name'] ?? '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
                        <p class="description">Used in SMS, cancellation emails, and the homepage. Defaults to the WordPress site name.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="practitioner_name">Practitioner Name</label></th>
                    <td>
                        <input type="text" id="practitioner_name" name="caswell_settings[practitioner_name]" value="<?php echo esc_attr( $o['practitioner_name'] ?? '' ); ?>" class="regular-text" placeholder="Your Name" />
                        <p class="description">Displayed on the homepage "About" section and used in calendar events.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="service_type">Service Type</label></th>
                    <td>
                        <input type="text" id="service_type" name="caswell_settings[service_type]" value="<?php echo esc_attr( $o['service_type'] ?? '' ); ?>" class="regular-text" placeholder="massage" />
                        <p class="description">Used in calendar event descriptions, email templates, and the homepage. Examples: "massage", "therapy", "consultation".</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="gcal_shared_event_title">Shared Calendar — Event Title</label></th>
                    <td>
                        <input type="text" id="gcal_shared_event_title" name="caswell_settings[gcal_shared_event_title]" value="<?php echo esc_attr( $o['gcal_shared_event_title'] ?? '' ); ?>" class="regular-text" placeholder="{practitioner}: {client_short} ({duration} min)" />
                        <p class="description">Format for events written to the <strong>shared</strong> calendar. Defaults to <code>{practitioner}: {client_short} ({duration} min)</code> (e.g. "Ryan: Jane S. (60 min)"). Placeholders: <code>{practitioner}</code>, <code>{client}</code>, <code>{client_first}</code>, <code>{client_short}</code> (first + last initial), <code>{duration}</code>, <code>{service}</code>.</p>
                    </td>
                </tr>
                <tr>
                    <th>Personal Calendar — Also Create Event?</th>
                    <td>
                        <label>
                            <input type="checkbox" name="caswell_settings[enable_primary_calendar_event]" value="1" <?php checked( ! empty( $o['enable_primary_calendar_event'] ) ); ?> />
                            Also create a copy of each booking on the OAuth user's <strong>primary</strong> Google Calendar
                        </label>
                        <p class="description">Off by default. When on, every confirmed booking is written to both the shared calendar (always) and the practitioner's personal calendar. Use the field below to format the personal-calendar title.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="gcal_event_title">Personal Calendar — Event Title</label></th>
                    <td>
                        <input type="text" id="gcal_event_title" name="caswell_settings[gcal_event_title]" value="<?php echo esc_attr( $o['gcal_event_title'] ?? '' ); ?>" class="regular-text" placeholder="{practitioner} Appointment — {client}" />
                        <p class="description">Only used when "Also create event on personal calendar" is on. Default: <code>{practitioner} Appointment — {client}</code>. Same placeholders as above.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="booking_page_title">Booking Page Title</label></th>
                    <td>
                        <input type="text" id="booking_page_title" name="caswell_settings[booking_page_title]" value="<?php echo esc_attr( $o['booking_page_title'] ?? '' ); ?>" class="regular-text" placeholder="Book an Appointment" />
                        <p class="description">Title shown on the standalone booking page. Only applies on new installs or if the page title is reset manually.</p>
                    </td>
                </tr>
            </table>

            <h3>Homepage Content</h3>
            <table class="form-table">
                <tr>
                    <th><label for="hero_tagline">Hero Tagline</label></th>
                    <td><input type="text" id="hero_tagline" name="caswell_settings[hero_tagline]" value="<?php echo esc_attr( $o['hero_tagline'] ?? '' ); ?>" class="large-text" placeholder="Therapeutic Massage for Body &amp; Mind" /></td>
                </tr>
                <tr>
                    <th><label for="hero_subtitle">Hero Subtitle</label></th>
                    <td><input type="text" id="hero_subtitle" name="caswell_settings[hero_subtitle]" value="<?php echo esc_attr( $o['hero_subtitle'] ?? '' ); ?>" class="large-text" placeholder="Professional, therapeutic massage tailored to your needs." /></td>
                </tr>
                <tr>
                    <th><label for="practitioner_bio">Practitioner Bio</label></th>
                    <td><textarea id="practitioner_bio" name="caswell_settings[practitioner_bio]" rows="5" class="large-text"><?php echo esc_textarea( $o['practitioner_bio'] ?? $o['ryan_bio'] ?? '' ); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="practitioner_creds">Credentials / License</label></th>
                    <td><input type="text" id="practitioner_creds" name="caswell_settings[practitioner_credentials]" value="<?php echo esc_attr( $o['practitioner_credentials'] ?? $o['ryan_credentials'] ?? '' ); ?>" class="regular-text" placeholder="e.g. LMT — Licensed Massage Therapist" /></td>
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
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:6px">
                <h2 style="margin:0">Bookings</h2>
                <button type="button" class="button button-primary" id="caswell-new-booking-btn">+ New booking</button>
            </div>
            <p class="description">Reschedule, cancel, or add notes to any booking. Reschedule and cancel actions update Google Calendar and email the client; note edits are silent. Use <strong>+ New booking</strong> to schedule a client manually (e.g. a phone-in or walk-in) — the plugin will email and text them just like a self-service booking.</p>
            <?php
            global $wpdb;
            $recent = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}caswell_bookings ORDER BY start_datetime DESC LIMIT 100"
            );
            if ( $recent ) : ?>
            <table class="widefat striped caswell-bookings-table" style="margin-bottom:20px;">
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
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $recent as $b ) :
                        $is_cancelled = $b->status === 'cancelled';
                        $is_past      = caswell_local_ts($b->start_datetime) < time();
                        ?>
                    <tr data-booking-id="<?php echo (int) $b->id; ?>"
                        data-name="<?php echo esc_attr( $b->name ); ?>"
                        data-email="<?php echo esc_attr( $b->email ); ?>"
                        data-start="<?php echo esc_attr( $b->start_datetime ); ?>"
                        data-length="<?php echo (int) $b->session_length; ?>"
                        data-notes="<?php echo esc_attr( $b->notes ?? '' ); ?>"
                        data-status="<?php echo esc_attr( $b->status ); ?>">
                        <td data-label="ID"><?php echo (int) $b->id; ?></td>
                        <td data-label="Name"><?php echo esc_html( $b->name ); ?></td>
                        <td data-label="Email"><?php echo esc_html( $b->email ); ?></td>
                        <td data-label="When">
                            <?php echo esc_html( wp_date( 'M j, Y', caswell_local_ts($b->start_datetime) ) ); ?><br>
                            <small><?php echo esc_html( wp_date( 'g:i A', caswell_local_ts($b->start_datetime) ) ); ?> – <?php echo esc_html( wp_date( 'g:i A', caswell_local_ts($b->end_datetime) ) ); ?></small>
                        </td>
                        <td data-label="Length"><?php echo (int) $b->session_length; ?> min</td>
                        <td data-label="Status"><span style="color:<?php echo $b->status === 'confirmed' ? '#27ae60' : ( $is_cancelled ? '#c0392b' : '#e67e22' ); ?>;font-weight:600;"><?php echo esc_html( ucfirst( $b->status ) ); ?></span></td>
                        <td data-label="Payment"><?php echo esc_html( ucfirst( $b->payment_method ) ); ?> — <?php echo esc_html( ucfirst( $b->payment_status ) ); ?></td>
                        <td data-label="Notes" class="caswell-notes-cell"><?php echo esc_html( wp_trim_words( $b->notes ?? '', 12 ) ); ?></td>
                        <td class="caswell-actions-cell">
                            <?php if ( ! $is_cancelled && ! $is_past ): ?>
                                <button type="button" class="button button-small caswell-act-reschedule">Reschedule</button>
                                <button type="button" class="button button-small caswell-act-cancel" style="color:#a00">Cancel</button>
                            <?php endif; ?>
                            <button type="button" class="button button-small caswell-act-notes">Notes</button>
                            <button type="button" class="button button-small caswell-act-delete" style="color:#a00" title="Permanently remove this booking from the database">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="description">Showing 100 bookings, most recent date first.</p>
            <?php else : ?>
            <p>No bookings yet.</p>
            <?php endif; ?>
        </div>

        <!-- ── Tools ───────────────────────────────────────────────────── -->
        <div id="tab-tools" class="caswell-tab-content">
            <h2>Tools &amp; Diagnostics</h2>

            <h3>Email Delivery Test</h3>
            <p class="description">If clients aren't receiving confirmation emails, run this to check whether <code>wp_mail</code> can hand off messages from your server. Most "no email arrives" issues on shared hosting (Bluehost, etc.) are because the host silently drops outbound mail — fix is to install an SMTP plugin like WP Mail SMTP and route through SendGrid/Mailgun/Gmail.</p>
            <p>
                <input type="email" id="caswell-test-email-to" placeholder="your@email.com" class="regular-text" style="max-width:280px" />
                <button type="button" class="button" id="caswell-test-email-btn">Send test email</button>
                <span id="caswell-test-email-msg" style="margin-left:12px"></span>
            </p>

            <h3>Shared Calendar — Write Test</h3>
            <p class="description">Verifies that the OAuth Google account has <strong>"Make changes to events"</strong> permission on the shared calendar. Creates a one-off test event 10 years out and immediately deletes it. If your real bookings aren't appearing on the shared calendar, run this — most likely the calendar is shared as read-only.</p>
            <p>
                <button type="button" class="button" id="caswell-test-shared-cal">Run write test</button>
                <span id="caswell-test-shared-cal-msg" style="margin-left:12px"></span>
            </p>

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

    <!--
        Modals live OUTSIDE the settings <form>. They contain their own forms
        which can't be HTML-nested inside the outer `<form action="options.php">`
        — browsers strip the inner <form> tag, which causes the modal's submit
        buttons to fall through to the outer form and navigate to options.php.
    -->
    <div id="caswell-new-modal" class="caswell-modal" hidden>
        <div class="caswell-modal-backdrop"></div>
        <div class="caswell-modal-card">
            <h3 style="margin-top:0">Schedule a client manually</h3>
            <p class="description" style="margin-top:0">Fill in client details and the appointment time. The plugin will create Google Calendar events and (if enabled) email + text the client.</p>
            <form id="caswell-new-booking-form">
                <div class="caswell-modal-fields" style="grid-template-columns:1fr 1fr">
                    <label class="caswell-modal-row" style="grid-column:1/-1">
                        <span>Client name</span>
                        <input type="text" name="name" required>
                    </label>
                    <label class="caswell-modal-row">
                        <span>Email</span>
                        <input type="email" name="email" required>
                    </label>
                    <label class="caswell-modal-row">
                        <span>Phone (for SMS)</span>
                        <input type="tel" name="phone">
                    </label>
                    <label class="caswell-modal-row">
                        <span>Date</span>
                        <input type="date" name="date" required>
                    </label>
                    <label class="caswell-modal-row">
                        <span>Start time</span>
                        <input type="time" name="time" step="300" required>
                    </label>
                    <label class="caswell-modal-row">
                        <span>Length (min)</span>
                        <input type="number" name="length" min="15" step="5" value="60" required>
                    </label>
                    <label class="caswell-modal-row" style="grid-column:1/-1">
                        <span>Notes (private)</span>
                        <textarea name="notes" rows="2" placeholder="Anything Ryan should know — areas to focus on, etc."></textarea>
                    </label>
                    <label class="caswell-modal-row caswell-row-email" style="grid-column:1/-1">
                        <input type="checkbox" name="send_notifications" value="1" checked>
                        Email + text the client a confirmation
                    </label>
                </div>
                <div class="caswell-modal-actions">
                    <button type="button" class="button caswell-modal-cancel" data-modal="caswell-new-modal">Cancel</button>
                    <button type="submit" class="button button-primary" id="caswell-new-submit">Create booking</button>
                </div>
                <p class="caswell-modal-msg" style="min-height:1.2em;color:#a00"></p>
            </form>
        </div>
    </div>

    <div id="caswell-modal" class="caswell-modal" hidden>
        <div class="caswell-modal-backdrop"></div>
        <div class="caswell-modal-card">
            <h3 id="caswell-modal-title" style="margin-top:0">Reschedule booking</h3>
            <p class="description" id="caswell-modal-sub"></p>
            <form id="caswell-modal-form">
                <input type="hidden" name="booking_id" value="">
                <div class="caswell-modal-fields">
                    <label class="caswell-modal-row caswell-row-date">
                        <span>New date</span>
                        <input type="date" name="new_date" required>
                    </label>
                    <label class="caswell-modal-row caswell-row-time">
                        <span>New start time</span>
                        <input type="time" name="new_time" required step="300">
                    </label>
                    <label class="caswell-modal-row caswell-row-length">
                        <span>Length (min)</span>
                        <input type="number" name="length" min="15" step="5" required>
                    </label>
                    <label class="caswell-modal-row caswell-row-notes" style="grid-column:1/-1">
                        <span>Notes (private — not in client email)</span>
                        <textarea name="notes" rows="2"></textarea>
                    </label>
                    <label class="caswell-modal-row caswell-row-message" style="grid-column:1/-1">
                        <span>Personal note to client (optional, included in reschedule email)</span>
                        <textarea name="message" rows="3" placeholder="Sorry for the change — happy to find another time if this doesn't work."></textarea>
                    </label>
                    <label class="caswell-modal-row caswell-row-email" style="grid-column:1/-1">
                        <input type="checkbox" name="send_email" value="1" checked> Email the client about this change
                    </label>
                </div>
                <div class="caswell-modal-actions">
                    <button type="button" class="button caswell-modal-cancel">Cancel</button>
                    <button type="submit" class="button button-primary" id="caswell-modal-submit">Save</button>
                </div>
                <p class="caswell-modal-msg" style="min-height:1.2em;color:#a00"></p>
            </form>
        </div>
    </div>
</div>

<style>
.caswell-tabs { display:flex; gap:4px; border-bottom:2px solid #2271b1; margin-bottom:20px; flex-wrap:wrap; }
.caswell-tab { padding:8px 16px; background:#f0f0f1; border:1px solid #c3c4c7; border-bottom:none; text-decoration:none; color:#1d2327; border-radius:4px 4px 0 0; }
.caswell-tab.active { background:#fff; border-bottom-color:#fff; font-weight:600; color:#2271b1; }
.caswell-tab-content { display:none; }
.caswell-tab-content.active { display:block; }

.caswell-bookings-table .caswell-actions-cell .button { margin: 2px 2px; }
.caswell-bookings-table .caswell-actions-cell { white-space: nowrap; }

/* Mobile — convert the table to stacked cards so the Actions column
   never falls off the right edge of the screen. Each booking becomes
   a vertical card with labelled rows, and the action buttons sit at
   the bottom of the card as full-width buttons. */
@media (max-width: 782px) {
    .caswell-bookings-table,
    .caswell-bookings-table thead,
    .caswell-bookings-table tbody,
    .caswell-bookings-table tr,
    .caswell-bookings-table td { display: block; width: 100%; box-sizing: border-box; }
    .caswell-bookings-table thead { display: none; }
    .caswell-bookings-table tr {
        background: #fff !important;
        border: 1px solid #dcdcde;
        border-radius: 8px;
        margin-bottom: 14px;
        padding: 12px 14px;
        box-shadow: 0 1px 2px rgba(0,0,0,.03);
    }
    .caswell-bookings-table tr:nth-child(even) { background: #fff !important; }
    .caswell-bookings-table td {
        border: 0 !important;
        padding: 6px 0;
        position: relative;
        padding-left: 110px;
        text-align: left;
    }
    .caswell-bookings-table td::before {
        content: attr(data-label);
        position: absolute;
        left: 0; top: 6px;
        width: 100px;
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #555;
    }
    .caswell-bookings-table .caswell-actions-cell {
        padding-left: 0;
        padding-top: 12px;
        margin-top: 8px;
        border-top: 1px solid #f0f0f1 !important;
        white-space: normal;
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    .caswell-bookings-table .caswell-actions-cell::before { display: none; }
    .caswell-bookings-table .caswell-actions-cell .button {
        flex: 1 1 calc(50% - 4px);
        margin: 0;
        min-height: 38px;
    }
    .caswell-bookings-table .caswell-actions-cell .caswell-act-reschedule,
    .caswell-bookings-table .caswell-actions-cell .caswell-act-cancel {
        flex: 1 1 100%;
    }
    .caswell-bookings-table .caswell-notes-cell {
        font-style: italic;
        color: #555;
    }

    /* Modal: full-bleed on small screens for the date/time pickers. */
    .caswell-modal-card {
        width: calc(100% - 16px);
        padding: 18px 18px 22px;
        max-height: 95vh;
    }
    .caswell-modal-fields { grid-template-columns: 1fr; gap: 10px; }
    .caswell-modal-fields input[type="date"],
    .caswell-modal-fields input[type="time"],
    .caswell-modal-fields input[type="number"] { font-size: 16px; }
}

.caswell-modal { position: fixed; inset: 0; z-index: 100100; display: flex; align-items: center; justify-content: center; }
.caswell-modal[hidden] { display: none; }
.caswell-modal-backdrop { position: absolute; inset: 0; background: rgba(0,0,0,.45); }
.caswell-modal-card { position: relative; background: #fff; border-radius: 10px; padding: 24px 28px; box-shadow: 0 24px 60px rgba(0,0,0,.25); max-width: 640px; width: calc(100% - 40px); max-height: 90vh; overflow-y: auto; }
.caswell-modal-fields { display: grid; grid-template-columns: 1fr 1fr 120px; gap: 12px 14px; margin: 16px 0; }
.caswell-modal-fields .caswell-modal-row { display: flex; flex-direction: column; gap: 6px; }
.caswell-modal-fields .caswell-modal-row > span { font-size: 13px; font-weight: 500; color: #555; }
.caswell-modal-fields input[type="date"], .caswell-modal-fields input[type="time"], .caswell-modal-fields input[type="number"], .caswell-modal-fields textarea { width: 100%; padding: 8px 10px; border: 1px solid #c3c4c7; border-radius: 4px; font: inherit; box-sizing: border-box; }
.caswell-modal-fields textarea { resize: vertical; }
.caswell-modal-fields .caswell-row-email { flex-direction: row; align-items: center; gap: 8px; font-size: 14px; color: #444; }
.caswell-modal-fields .caswell-row-email > span { display: none; }
.caswell-modal-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 8px; }
@media (max-width: 600px) {
    .caswell-modal-fields { grid-template-columns: 1fr; }
}
</style>

<script>
(function(){
    var tabs = document.querySelectorAll('.caswell-tab');
    var contents = document.querySelectorAll('.caswell-tab-content');

    function activateTab(targetId) {
        var contentEl = document.getElementById(targetId);
        if (!contentEl) return false;
        tabs.forEach(function(t){ t.classList.remove('active'); });
        contents.forEach(function(c){ c.classList.remove('active'); });
        var matchingTab = document.querySelector('.caswell-tab[href="#' + targetId + '"]');
        if (matchingTab) matchingTab.classList.add('active');
        contentEl.classList.add('active');
        return true;
    }

    tabs.forEach(function(tab){
        tab.addEventListener('click', function(e){
            e.preventDefault();
            var target = this.getAttribute('href').replace('#','');
            if (activateTab(target)) {
                // Sync the URL hash so a page reload (e.g. after a booking
                // action AJAXes back and the JS calls location.reload())
                // returns the user to the same tab they were on.
                if (history && history.replaceState) {
                    history.replaceState(null, '', '#' + target);
                } else {
                    window.location.hash = target;
                }
            }
        });
    });

    // On initial load, honor the hash if present so reloads keep the tab.
    if (window.location.hash) {
        var initial = window.location.hash.replace('#','');
        activateTab(initial);
    }
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

// ── Booking actions: reschedule / cancel / notes / DST shift ──────────
(function($){
    var $modal  = $('#caswell-modal');
    var $form   = $('#caswell-modal-form');
    var $title  = $('#caswell-modal-title');
    var $sub    = $('#caswell-modal-sub');
    var $msg    = $modal.find('.caswell-modal-msg');
    var $submit = $('#caswell-modal-submit');

    function openModal(mode, $row) {
        var d = $row.data();
        $form[0].reset();
        $form.find('[name=booking_id]').val(d.bookingId);
        $msg.text('');

        var startDate = (d.start || '').slice(0, 10);
        var startTime = (d.start || '').slice(11, 16);

        $sub.text('#' + d.bookingId + ' · ' + d.name + ' · ' + d.email);

        if (mode === 'reschedule') {
            $title.text('Reschedule booking');
            $form.find('.caswell-row-date, .caswell-row-time, .caswell-row-length, .caswell-row-message, .caswell-row-email').show();
            $form.find('.caswell-row-notes').show();
            $form.find('[name=new_date]').val(startDate);
            $form.find('[name=new_time]').val(startTime);
            $form.find('[name=length]').val(d.length);
            $form.find('[name=notes]').val(d.notes || '');
            $form.find('[name=send_email]').prop('checked', true);
            $submit.text('Save & email client');
            $form.data('action', 'caswell_admin_reschedule');
        } else if (mode === 'notes') {
            $title.text('Edit notes');
            $form.find('.caswell-row-date, .caswell-row-time, .caswell-row-length, .caswell-row-message, .caswell-row-email').hide();
            $form.find('.caswell-row-notes').show();
            $form.find('[name=notes]').val(d.notes || '');
            $submit.text('Save notes');
            $form.data('action', 'caswell_admin_update_notes');
        }

        $modal.removeAttr('hidden');
    }

    function closeModal() {
        $modal.attr('hidden', true);
    }

    $modal.on('click', '.caswell-modal-backdrop, .caswell-modal-cancel', closeModal);
    $(document).on('keydown', function(e){
        if (e.key === 'Escape' && !$modal.is('[hidden]')) closeModal();
    });

    $('.caswell-bookings-table').on('click', '.caswell-act-reschedule', function(){
        openModal('reschedule', $(this).closest('tr'));
    });
    $('.caswell-bookings-table').on('click', '.caswell-act-notes', function(){
        openModal('notes', $(this).closest('tr'));
    });

    $('.caswell-bookings-table').on('click', '.caswell-act-shift', function(){
        var $btn  = $(this);
        var $row  = $btn.closest('tr');
        var hours = parseInt($btn.data('shift'), 10);
        var label = (hours > 0 ? '+' : '') + hours + 'h';
        if (!confirm('Shift this booking by ' + label + ' and email the client? Use this for daylight-savings adjustments.')) return;
        $btn.prop('disabled', true).text('…');
        $.post(caswellAdminData.ajax_url, {
            action:      'caswell_admin_reschedule',
            nonce:       caswellAdminData.nonce,
            booking_id:  $row.data('booking-id'),
            shift_hours: hours,
            send_email:  1,
            message:     'Daylight-savings adjustment — appointment time shifted by ' + label + '.'
        }, function(res){
            if (res.success) {
                location.reload();
            } else {
                alert((res.data && res.data.message) || 'Error.');
                $btn.prop('disabled', false).text(label.replace('+','+').replace('-','−'));
            }
        }).fail(function(){
            alert('Network error.');
            $btn.prop('disabled', false);
        });
    });

    $('.caswell-bookings-table').on('click', '.caswell-act-delete', function(){
        var $row = $(this).closest('tr');
        if (!confirm('Permanently delete this booking?\n\nClient: ' + $row.data('name') + '\n\nThis cannot be undone. The Google Calendar events will also be removed.')) return;
        $.post(caswellAdminData.ajax_url, {
            action:     'caswell_admin_delete_booking',
            nonce:      caswellAdminData.nonce,
            booking_id: $row.data('booking-id')
        }, function(res){
            if (res.success) {
                $row.fadeOut(150, function(){ $(this).remove(); });
            } else {
                alert((res.data && res.data.message) || 'Error.');
            }
        });
    });

    $('.caswell-bookings-table').on('click', '.caswell-act-cancel', function(){
        var $row = $(this).closest('tr');
        var send = confirm('Cancel this booking and email the client? Press OK to email, Cancel to silently cancel without email.\n\nClient: ' + $row.data('name'));
        // Two-step: confirm cancellation, then ask about email
        if (send && !confirm('Are you sure you want to cancel this booking?')) return;
        $.post(caswellAdminData.ajax_url, {
            action:     'caswell_admin_cancel',
            nonce:      caswellAdminData.nonce,
            booking_id: $row.data('booking-id'),
            send_email: send ? 1 : 0
        }, function(res){
            if (res.success) {
                location.reload();
            } else {
                alert((res.data && res.data.message) || 'Error.');
            }
        });
    });

    $form.on('submit', function(e){
        e.preventDefault();
        $msg.text('').css('color', '#a00');
        $submit.prop('disabled', true);

        var payload = { nonce: caswellAdminData.nonce, action: $form.data('action') };
        $form.serializeArray().forEach(function(f){ payload[f.name] = f.value; });
        // Checkboxes that aren't checked aren't in serializeArray — explicitly send 0
        if (!$form.find('[name=send_email]:checked').length) payload.send_email = 0;

        $.post(caswellAdminData.ajax_url, payload, function(res){
            if (res.success) {
                $msg.css('color', '#27ae60').text(res.data.message || 'Saved.');
                setTimeout(function(){ location.reload(); }, 700);
            } else {
                $msg.text((res.data && res.data.message) || 'Error.');
                $submit.prop('disabled', false);
            }
        }).fail(function(){
            $msg.text('Network error.');
            $submit.prop('disabled', false);
        });
    });

    // Manual new-booking modal
    var $newModal  = $('#caswell-new-modal');
    var $newForm   = $('#caswell-new-booking-form');
    var $newMsg    = $newModal.find('.caswell-modal-msg');
    var $newSubmit = $('#caswell-new-submit');

    function openNewModal() {
        $newForm[0].reset();
        $newMsg.text('').css('color','#a00');
        // Default the date input to today
        var d = new Date();
        var iso = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
        $newForm.find('[name=date]').val(iso);
        $newForm.find('[name=length]').val(60);
        $newForm.find('[name=send_notifications]').prop('checked', true);
        $newModal.removeAttr('hidden');
    }
    function closeNewModal() { $newModal.attr('hidden', true); }

    $('#caswell-new-booking-btn').on('click', openNewModal);
    $newModal.on('click', '.caswell-modal-backdrop, .caswell-modal-cancel', closeNewModal);
    $(document).on('keydown', function(e){
        if (e.key === 'Escape' && !$newModal.is('[hidden]')) closeNewModal();
    });

    $newForm.on('submit', function(e){
        e.preventDefault();
        $newMsg.text('').css('color','#a00');
        $newSubmit.prop('disabled', true).text('Creating…');

        var payload = { action: 'caswell_admin_new_booking', nonce: caswellAdminData.nonce };
        $newForm.serializeArray().forEach(function(f){ payload[f.name] = f.value; });
        if (!$newForm.find('[name=send_notifications]:checked').length) payload.send_notifications = 0;

        $.post(caswellAdminData.ajax_url, payload, function(res){
            if (res.success) {
                $newMsg.css('color','#27ae60').text(res.data.message || 'Created.');
                setTimeout(function(){ location.reload(); }, 900);
            } else {
                $newMsg.text((res.data && res.data.message) || 'Error.');
                $newSubmit.prop('disabled', false).text('Create booking');
            }
        }).fail(function(){
            $newMsg.text('Network error.');
            $newSubmit.prop('disabled', false).text('Create booking');
        });
    });

    // Test email
    $('#caswell-test-email-btn').on('click', function(){
        var $btn = $(this);
        var $msg = $('#caswell-test-email-msg');
        var to   = $('#caswell-test-email-to').val().trim();
        if (!to) { $msg.css('color','#a00').text('Enter an email address.'); return; }
        $btn.prop('disabled', true);
        $msg.css('color','#555').text('Sending…');
        $.post(caswellAdminData.ajax_url, {
            action: 'caswell_admin_test_email',
            nonce:  caswellAdminData.nonce,
            to:     to
        }, function(res){
            $btn.prop('disabled', false);
            if (res.success) {
                $msg.css('color','#27ae60').html('✓ ' + (res.data.message || 'Sent.'));
            } else {
                $msg.css('color','#a00').html('✗ ' + (res.data && res.data.message ? res.data.message : 'Failed.'));
            }
        }).fail(function(){
            $btn.prop('disabled', false);
            $msg.css('color','#a00').text('Request failed.');
        });
    });

    // Test shared calendar
    $('#caswell-test-shared-cal').on('click', function(){
        var $btn = $(this);
        var $msg = $('#caswell-test-shared-cal-msg');
        $btn.prop('disabled', true);
        $msg.css('color', '#555').text('Testing… (creating + deleting a one-off event)');
        $.post(caswellAdminData.ajax_url, {
            action: 'caswell_admin_test_shared_cal',
            nonce:  caswellAdminData.nonce
        }, function(res){
            $btn.prop('disabled', false);
            if (res.success) {
                $msg.css('color', '#27ae60').html('✓ ' + (res.data.message || 'Success.'));
            } else {
                $msg.css('color', '#a00').html('✗ ' + (res.data && res.data.message ? res.data.message : 'Failed.'));
            }
        }).fail(function(){
            $btn.prop('disabled', false);
            $msg.css('color', '#a00').text('Request failed — check the debug log.');
        });
    });
}(jQuery));
</script>
