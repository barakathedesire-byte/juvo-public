<div class="modal fade" id="acceptDeliveryModal" tabindex="-1" aria-labelledby="acceptDeliveryTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content deliveries-modal">
            <div class="modal-body">
                <span class="deliveries-modal-icon"><i class="fas fa-motorcycle" aria-hidden="true"></i></span>
                <h2 id="acceptDeliveryTitle">Accept delivery #<span data-accept-ref>—</span>?</h2>
                <p data-accept-route class="deliveries-modal-route"></p>
                <p class="deliveries-modal-copy">You are confirming that you can collect this order and deliver it to the customer.</p>

                <div class="delivery-form-error" data-accept-error role="alert" hidden></div>

                <div class="deliveries-modal-actions">
                    <button type="button" class="delivery-btn delivery-btn-ghost" data-bs-dismiss="modal">Not now</button>
                    <button type="button" class="delivery-btn delivery-btn-primary" data-accept-confirm>
                        <span class="delivery-btn-label">Accept delivery</span>
                        <span class="delivery-spinner" aria-hidden="true"></span>
                    </button>
                </div>

                <button type="button" class="delivery-refresh-link" data-accept-refresh hidden>
                    Refresh my deliveries <i class="fas fa-rotate-right" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </div>
</div>
