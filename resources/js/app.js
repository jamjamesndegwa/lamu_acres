import * as Turbo from '@hotwired/turbo'
import Swal from 'sweetalert2'
import 'sweetalert2/dist/sweetalert2.min.css'

Turbo.session.drive = false

document.addEventListener('click', (event) => {
    const menu = document.getElementById('public-mobile-menu')
    if (!(menu instanceof HTMLDialogElement)) return

    if (event.target.closest('[data-public-menu-open]')) {
        menu.showModal()
        document.querySelector('[data-public-menu-open]')?.setAttribute('aria-expanded', 'true')
        return
    }

    if (event.target.closest('[data-public-menu-close]') || event.target.closest('#public-mobile-menu a')) {
        menu.close()
    } else if (event.target === menu) {
        const bounds = menu.getBoundingClientRect()
        if (event.clientX < bounds.left || event.clientX > bounds.right) menu.close()
    }
})

document.addEventListener('close', (event) => {
    if (event.target.id === 'public-mobile-menu') {
        document.querySelector('[data-public-menu-open]')?.setAttribute('aria-expanded', 'false')
    }
}, true)

window.matchMedia('(min-width: 1280px)').addEventListener('change', (event) => {
    if (event.matches) document.getElementById('public-mobile-menu')?.close()
})

document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-dialog-open]')
    if (opener) {
        const dialog = document.getElementById(opener.dataset.dialogOpen)
        if (dialog instanceof HTMLDialogElement) {
            dialog.showModal()
            opener.setAttribute('aria-expanded', 'true')
        }
        return
    }

    const dialog = event.target.closest('dialog[open]')
    if (!dialog || dialog.id === 'public-mobile-menu') return

    if (event.target.closest('[data-dialog-close]') || event.target === dialog) {
        dialog.close()
    }
})

document.addEventListener('close', (event) => {
    if (event.target instanceof HTMLDialogElement && event.target.id) {
        document.querySelector(`[data-dialog-open="${event.target.id}"]`)?.setAttribute('aria-expanded', 'false')
    }
}, true)

window.matchMedia('(min-width: 1024px)').addEventListener('change', (event) => {
    if (event.matches) document.getElementById('land-filters-dialog')?.close()
})

document.addEventListener('change', (event) => {
    if (event.target instanceof HTMLSelectElement && event.target.matches('[data-auto-submit]')) {
        event.target.form?.requestSubmit()
    }
})

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
document.addEventListener('livewire:navigated', showFlash)
document.addEventListener('DOMContentLoaded', showFlash)

function sameOriginPage(url) {
    return url.origin === window.location.origin
}

function openModalFrame(link, url) {
    const id = link.dataset.turboFrame

    if (!id || id === '_top') {
        return false
    }

    const frame = document.getElementById(id)

    if (!(frame instanceof HTMLElement) || frame.tagName !== 'TURBO-FRAME') {
        return false
    }

    if (frame.getAttribute('src') === url.href && typeof frame.reload === 'function') {
        frame.reload()
    } else {
        frame.src = url.href
    }

    return true
}

function wantsNativeVisit(link, url) {
    if (link.hasAttribute('download') || link.dataset.turbo === 'false') {
        return true
    }

    if (link.dataset.turboFrame === '_top') {
        return true
    }

    if (link.target && link.target !== '' && link.target !== '_self') {
        return true
    }

    if (! sameOriginPage(url)) {
        return true
    }

    if (url.pathname === window.location.pathname && url.search === window.location.search) {
        return true
    }

    return false
}

document.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0) {
        return
    }

    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return
    }

    const link = event.target.closest('a[href]')

    if (! (link instanceof HTMLAnchorElement)) {
        return
    }

    const url = new URL(link.href, window.location.href)

    if (openModalFrame(link, url)) {
        event.preventDefault()

        return
    }

    if (! window.Livewire?.navigate || wantsNativeVisit(link, url)) {
        return
    }

    event.preventDefault()
    window.Livewire.navigate(url.href)
})

document.addEventListener('submit', (event) => {
    const form = event.target

    if (! (form instanceof HTMLFormElement) || event.defaultPrevented || form.dataset.turbo === 'false') {
        return
    }

    if ((form.getAttribute('method') || 'get').toLowerCase() !== 'get' || ! window.Livewire?.navigate) {
        return
    }

    const action = new URL(form.action || window.location.href, window.location.href)
    const params = new URLSearchParams()

    for (const [key, value] of new FormData(form).entries()) {
        if (typeof value === 'string') {
            params.append(key, value)
        }
    }

    action.search = params.toString()
    event.preventDefault()
    window.Livewire.navigate(action.href)
})

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

    if (!(form instanceof HTMLFormElement) || event.defaultPrevented) {
        return
    }

    if ((form.getAttribute('method') || 'get').toLowerCase() === 'get') {
        return
    }

    document.querySelector('[data-page-loading]')?.removeAttribute('hidden')
})

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
