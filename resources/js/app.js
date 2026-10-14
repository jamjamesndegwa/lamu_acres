import * as Turbo from '@hotwired/turbo'
import Swal from 'sweetalert2'
import 'sweetalert2/dist/sweetalert2.min.css'

Turbo.session.drive = false

const swalDefaults = {
    confirmButtonColor: '#143028',
    cancelButtonColor: '#78716c',
}

function notify({ icon = 'success', title, text }) {
    return Swal.fire({
        ...swalDefaults,
        icon,
        title,
        text,
    })
}

function confirmAction(message) {
    return Swal.fire({
        ...swalDefaults,
        icon: 'warning',
        title: message,
        showCancelButton: true,
        confirmButtonText: 'Continue',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        focusCancel: true,
    })
}

function showFlash() {
    document.querySelectorAll('[data-flash]').forEach((node) => {
        const message = node.dataset.message

        if (!message) {
            return
        }

        notify({
            icon: node.dataset.flash || 'success',
            title: message,
        })

        node.remove()
    })
}

function clearModalFrame() {
    const frame = document.getElementById('modal')

    if (frame) {
        frame.innerHTML = ''
    }
}

document.addEventListener('turbo:load', showFlash)
document.addEventListener('turbo:frame-load', showFlash)
document.addEventListener('DOMContentLoaded', showFlash)

document.addEventListener('click', (event) => {
    const closer = event.target.closest('[data-close-modal]')

    if (closer) {
        event.preventDefault()
        clearModalFrame()

        return
    }

    const confirmable = event.target.closest('[data-confirm]')

    if (!(confirmable instanceof HTMLElement) || confirmable.tagName === 'FORM') {
        return
    }

    if (confirmable.dataset.confirmed === 'true') {
        confirmable.dataset.confirmed = 'false'

        return
    }

    const message = confirmable.dataset.confirm

    if (!message) {
        return
    }

    event.preventDefault()
    event.stopPropagation()

    confirmAction(message).then((result) => {
        if (!result.isConfirmed) {
            return
        }

        confirmable.dataset.confirmed = 'true'

        if (confirmable instanceof HTMLAnchorElement && confirmable.href) {
            confirmable.click()

            return
        }

        if (confirmable instanceof HTMLButtonElement) {
            confirmable.click()
        }
    })
}, true)

document.addEventListener('submit', (event) => {
    const form = event.target

    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed === 'true') {
        return
    }

    event.preventDefault()
    event.stopPropagation()

    confirmAction(form.dataset.confirm).then((result) => {
        if (!result.isConfirmed) {
            return
        }

        form.dataset.confirmed = 'true'
        form.requestSubmit()
    })
}, true)

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        clearModalFrame()
    }
})

window.LamuAcres = {
    notify,
    confirm: confirmAction,
    clearModal: clearModalFrame,
}
