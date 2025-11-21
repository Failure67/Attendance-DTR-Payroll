class ModalStep {
    constructor(modalId, config = {}) {
        this.$modal = $(`#${modalId}`);
        this.currentStep = 1;
        this.config = {
            totalSteps: config.totalSteps || 2,
            nextButton: '[data-action="next"]',
            previousButton: '[data-action="back"]',
            submitButton: '[data-action"submit"]',
            discardButton: '[data-action="discard"]',
            stepContainer: '.modal-body-container',
            onStepChange: null,
            onBeforeNext: null,
            onBeforePrevious: null,
            onSubmit: null,
            ...config
        };

        this.init();
    }

    init() {
        this.showStep(1);
        this.bindEvents();
        this.$modal.on('hidden.bs.modal', () => {
            this.reset();
        });
    }

    bindEvents() {
        this.$modal.on('click', this.config.nextButton, (e) => {
            e.preventDefault();
            this.next();
        });
        this.$modal.on('click', this.config.previousButton, (e) => {
            e.preventDefault();
            this.prev();
        });
        this.$modal.on('click', this.config.discardButton, (e) => {
            e.preventDefault();
            this.discard();
        });
        this.$modal.on('click', this.config.submitButton, (e) => {
            e.preventDefault();
            this.submit();
        });
    }

    showStep(step) {
        this.currentStep = step;
        this.$modal.find(this.config.stepContainer).hide();
        this.$modal.find(this.config.stepContainer).eq(step - 1).show();
        this.updateButtons();
        if (this.config.onStepChange) this.config.onStepChange.call(this, step);
    }

    updateButtons() {
        const $nextBtn = this.$modal.find(this.config.nextButton);
        const $prevBtn = this.$modal.find(this.config.previousButton);
        const $submitBtn = this.$modal.find(this.config.submitButton);

        $nextBtn.hide();
        $prevBtn.hide();
        $submitBtn.hide();

        if (this.currentStep === 1) $nextBtn.show();
        else if (this.currentStep < this.config.totalSteps) {
            $prevBtn.show();
            $nextBtn.show();
        } else if (this.currentStep === this.config.totalSteps) {
            $prevBtn.show();
            $submitBtn.show();
        }
    }

    next() {
        if (this.config.onBeforeNext) {
            const proceed = this.config.onBeforeNext.call(this, this.currentStep);
            if (proceed === false) return;
        }
        if (this.currentStep < this.config.totalSteps) {
            this.showStep(this.currentStep + 1);
        }
    }

    prev() {
        if (this.config.onBeforePrevious) {
            this.config.onBeforePrevious.call(this, this.currentStep);
        }
        if (this.currentStep > 1) {
            this.showStep(this.currentStep - 1);
        }
    }

    discard() {
        this.reset();
        this.$modal.modal('hide');
    }

    submit() {
        if (this.config.onSubmit) {
            const proceed = this.config.onSubmit.call(this);
            if (proceed === false) return;
        }
        this.$modal.find('form').submit();
    }

    reset() {
        this.showStep(1);
        this.$modal.find('form')[0].reset();
    }

    getCurrentStep() {
        return this.currentStep;
    }

    isLastStep() {
        return this.currentStep === this.config.totalSteps;
    }

    isFirstStep() {
        return this.currentStep === 1;
    }
}