@props(['channelToggle' => false])

<script>
    (() => {
        const form = document.getElementById('campaign-create-form') ?? document.querySelector('[data-campaign-recipients-form]');
        if (! form) {
            return;
        }

        const panelFor = (button) => button.closest('div[id^="recipient-panel"]') ?? form;

        const visibleRecipientInputs = (panel) => panel.querySelectorAll('li[data-partner-roles] input[name="recipient_ids[]"]:not(:disabled)');

        form.querySelectorAll('[data-select-eligible-recipients]').forEach((button) => {
            button.addEventListener('click', () => {
                visibleRecipientInputs(panelFor(button)).forEach((input) => {
                    input.checked = true;
                });
            });
        });

        form.querySelectorAll('[data-select-role]').forEach((button) => {
            button.addEventListener('click', () => {
                const role = button.dataset.selectRole;
                const panel = panelFor(button);
                panel.querySelectorAll('li[data-partner-roles]').forEach((item) => {
                    const roles = (item.dataset.partnerRoles ?? '').split(/\s+/).filter(Boolean);
                    const input = item.querySelector('input[name="recipient_ids[]"]');
                    if (! input || input.disabled) {
                        return;
                    }
                    input.checked = roles.includes(role);
                });
            });
        });

        form.querySelectorAll('[data-clear-all-recipients]').forEach((button) => {
            button.addEventListener('click', () => {
                panelFor(button).querySelectorAll('input[name="recipient_ids[]"]').forEach((input) => {
                    input.checked = false;
                });
            });
        });

        @if ($channelToggle)
        const channel = document.getElementById('channel');
        const smsPanel = document.getElementById('recipient-panel-sms');
        const emailPanel = document.getElementById('recipient-panel-email');

        const syncChannelPanels = () => {
            const isEmail = channel.value === '{{ \App\Enums\MessageChannel::Email->value }}';
            if (smsPanel) {
                smsPanel.hidden = isEmail;
            }
            if (emailPanel) {
                emailPanel.hidden = ! isEmail;
            }

            form.querySelectorAll('#recipient-panel-sms input[name="recipient_ids[]"]').forEach((input) => {
                input.disabled = isEmail;
            });
            form.querySelectorAll('#recipient-panel-email input[name="recipient_ids[]"]').forEach((input) => {
                input.disabled = ! isEmail;
            });
        };

        if (channel && smsPanel && emailPanel) {
            channel.addEventListener('change', syncChannelPanels);
            syncChannelPanels();
        }
        @endif
    })();
</script>
