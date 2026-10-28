import * as Turbo from '@hotwired/turbo'
import Swal from 'sweetalert2'
import 'sweetalert2/dist/sweetalert2.min.css'

Turbo.session.drive = false

function validateUploadSize(input) {
    const maxKilobytes = Number(input.dataset.maxKilobytes)
    if (!Number.isFinite(maxKilobytes) || maxKilobytes <= 0) return true

    const isTooLarge = Array.from(input.files ?? []).some((file) => file.size > maxKilobytes * 1024)
    const fileLabel = input.name.replaceAll('_', ' ')
    const maxMegabytes = maxKilobytes / 1024
    const maxSize = Number.isInteger(maxMegabytes) ? `${maxMegabytes} MB` : `${maxKilobytes} KB`
    const message = `${fileLabel} must be ${maxSize} or smaller. Choose a smaller file before submitting.`
    const error = input.parentElement?.querySelector('[data-upload-size-error]')

    input.setCustomValidity(isTooLarge ? message : '')
    if (error) {
        error.textContent = isTooLarge ? message : ''
        error.hidden = !isTooLarge
    }

    return !isTooLarge
}

document.addEventListener('change', (event) => {
    const input = event.target
    if (!(input instanceof HTMLInputElement) || input.type !== 'file' || !input.dataset.maxKilobytes) return

    validateUploadSize(input)
})

document.addEventListener('submit', (event) => {
    const form = event.target
    if (!(form instanceof HTMLFormElement)) return

    const invalidInput = Array.from(form.querySelectorAll('input[type="file"][data-max-kilobytes]'))
        .find((input) => !validateUploadSize(input))

    if (!invalidInput) return

    event.preventDefault()
    event.stopImmediatePropagation()
    invalidInput.reportValidity()
}, true)

async function saveOwnerSubmission(url, data) {
    const response = await fetch(url, {
        method: 'POST', body: data, credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    const body = await response.text()
    let result = {}
    try { result = JSON.parse(body) } catch {}
    if (!response.ok || !result.id) {
        const details = Object.values(result.errors ?? {}).flat().join(' ')
        const status = `Request failed (HTTP ${response.status}${response.statusText ? ` ${response.statusText}` : ''}).`
        throw new Error(details || result.message || status)
    }
    return result
}

document.addEventListener('submit', async (event) => {
    const form = event.target
    if (!(form instanceof HTMLFormElement) || !form.matches('[data-owner-bulk-save]')) return
    event.preventDefault()
    event.stopImmediatePropagation()
    if (form.dataset.saving === 'true') return

    const progress = form.querySelector('[data-owner-save-progress]')
    const errors = form.querySelector('[data-owner-save-errors]')
    const details = new FormData(form)
    const token = form.querySelector('[name="_token"]').value
    const uploadForms = ['seller-document-uploads', 'seller-photo-uploads'].map((id) => document.getElementById(id)).filter(Boolean)
    const uploads = []
    for (const uploadForm of uploadForms) {
        for (const input of Array.from(uploadForm.elements)) {
            if (!(input instanceof HTMLInputElement) || input.type !== 'file') continue
            for (const file of input.files) {
                uploads.push({ input, file, action: uploadForm.action, type: input.dataset.documentType })
            }
        }
    }
    const controls = [form, ...uploadForms].flatMap((item) => Array.from(item.elements)).filter((element) => !element.disabled)
    controls.forEach((element) => { element.disabled = true })
    form.dataset.saving = 'true'
    errors.replaceChildren()
    let completed = 0
    const successfulInputs = new Set()

    try {
        progress.textContent = 'Saving all listing details…'
        await saveOwnerSubmission(form.action, details)
        for (const [index, upload] of uploads.entries()) {
            progress.textContent = `Details saved. Uploading file ${index + 1} of ${uploads.length}: ${upload.file.name}`
            const data = new FormData()
            data.set('_token', token)
            data.set(upload.type ? 'document' : 'photo', upload.file)
            if (upload.type) data.set('document_type', upload.type)
            try {
                await saveOwnerSubmission(upload.action, data)
                completed++
                successfulInputs.add(upload.input)
            } catch (error) {
                const item = document.createElement('li')
                item.textContent = `${upload.file.name}: ${error.message}`
                errors.append(item)
            }
        }
        for (const input of successfulInputs) {
            if (uploads.filter((upload) => upload.input === input).length === 1) input.value = ''
        }
        progress.textContent = `All listing details saved. ${completed} of ${uploads.length} selected files uploaded.`
        if (completed === uploads.length) {
            window.location.reload()
        } else {
            const item = document.createElement('li')
            item.textContent = 'Successful uploads have been saved. Select only the files that failed before retrying, or refresh to see the updated checklist.'
            errors.append(item)
        }
    } catch (error) {
        progress.textContent = 'Details could not be saved. Your selected files have not been uploaded.'
        const item = document.createElement('li')
        item.textContent = error.message
        errors.append(item)
    } finally {
        form.dataset.saving = 'false'
        controls.forEach((element) => { element.disabled = false })
    }
}, true)

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-preview-file]')
    if (!button) return
    const viewer = button.closest('[data-private-file-viewer]')
    const image = viewer.querySelector('[data-preview-image]')
    const pdf = viewer.querySelector('[data-preview-pdf]')
    const message = viewer.querySelector('[data-preview-message]')
    const download = viewer.querySelector('[data-preview-download]')
    viewer.querySelectorAll('[data-preview-file]').forEach((item) => item.setAttribute('aria-pressed', String(item === button)))
    viewer.querySelector('[data-preview-name]').textContent = button.dataset.fileName
    image.hidden = true
    pdf.hidden = true
    image.removeAttribute('src')
    pdf.removeAttribute('src')
    download.href = button.dataset.downloadUrl
    download.hidden = false
    const mime = button.dataset.fileMime || ''
    const name = button.dataset.fileName.toLowerCase()
    const isImage = ['image/jpeg', 'image/png', 'image/webp'].includes(mime) || (!mime && /\.(jpe?g|png|webp)$/.test(name))
    const isPdf = mime === 'application/pdf' || (!mime && name.endsWith('.pdf'))
    message.hidden = isImage || isPdf
    if (isImage) {
        image.alt = button.dataset.fileName
        image.onerror = () => {
            image.hidden = true
            message.hidden = false
            message.textContent = 'The image preview could not be loaded. Use the original file link below.'
        }
        image.src = button.dataset.previewUrl
        image.hidden = false
    } else if (isPdf) {
        pdf.src = button.dataset.previewUrl
        pdf.hidden = false
    } else {
        message.textContent = 'This file type cannot be displayed here. Open or download the original file below.'
    }
})

document.addEventListener('change', (event) => {
    const checkbox = event.target
    if (!(checkbox instanceof HTMLInputElement) || !checkbox.matches('[data-select-document-reviews]')) return

    Array.from(checkbox.form.elements).forEach((input) => {
        if (input instanceof HTMLInputElement && input.name === 'document_ids[]') {
            input.checked = checkbox.checked
        }
    })
})

document.addEventListener('submit', async (event) => {
    const form = event.target
    if (!(form instanceof HTMLFormElement) || !form.matches('[data-document-batch-upload]')) return

    event.preventDefault()
    event.stopImmediatePropagation()
    if (form.dataset.uploading === 'true') return

    const inputs = Array.from(form.elements).filter((input) => input instanceof HTMLInputElement && input.type === 'file')
    const selected = inputs.filter((input) => input.files.length > 0)
    const section = form.closest('section')
    const progress = section.querySelector('[data-document-upload-progress]')
    const errors = section.querySelector('[data-document-upload-errors]')
    errors.replaceChildren()

    if (selected.length === 0) {
        progress.textContent = 'Choose at least one document to upload.'
        return
    }

    const oversized = selected.filter((input) => input.files[0].size > 10 * 1024 * 1024)
    if (oversized.length > 0) {
        progress.textContent = 'Each document must be 10 MB or smaller. Choose smaller files and try again.'
        return
    }

    form.dataset.uploading = 'true'
    const button = Array.from(form.elements).find((element) => element instanceof HTMLButtonElement && element.type === 'submit')
    button.disabled = true
    inputs.forEach((input) => { input.disabled = true })
    let completed = 0

    try {
        for (const [index, input] of selected.entries()) {
            progress.textContent = `Uploading ${index + 1} of ${selected.length}: ${input.dataset.documentLabel}…`
            const data = new FormData()
            data.set('_token', form.querySelector('[name="_token"]').value)
            data.set('document_type', input.dataset.documentType)
            data.set('document', input.files[0])

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: data,
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                const body = await response.text()
                let result = {}
                try { result = JSON.parse(body) } catch {}
                if (!response.ok || !result.id) {
                    const details = Object.values(result.errors ?? {}).flat().join(' ')
                    const status = `Upload failed (HTTP ${response.status}${response.statusText ? ` ${response.statusText}` : ''}).`
                    throw new Error(details || result.message || status)
                }
                completed++
                input.value = ''
            } catch (error) {
                const item = document.createElement('li')
                item.textContent = `${input.dataset.documentLabel}: ${error.message}`
                errors.append(item)
            }
        }

        progress.textContent = `${completed} of ${selected.length} documents uploaded privately for staff review.`
        if (completed === selected.length) {
            window.location.hash = 'documents'
            window.location.reload()
        } else if (completed > 0) {
            const item = document.createElement('li')
            const link = document.createElement('a')
            link.href = window.location.href.split('#')[0] + '#documents'
            link.textContent = 'Refresh to see the uploaded documents, or retry the remaining files.'
            link.className = 'underline'
            item.append(link)
            errors.append(item)
        }
    } finally {
        form.dataset.uploading = 'false'
        button.disabled = false
        inputs.forEach((input) => { input.disabled = false })
    }
}, true)

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

function showPageLoading() {
    const indicator = document.querySelector('[data-page-loading]')

    if (! indicator) return

    indicator.hidden = false
    indicator.removeAttribute('aria-hidden')
}

function hidePageLoading() {
    const indicator = document.querySelector('[data-page-loading]')

    if (! indicator) return

    indicator.hidden = true
    indicator.setAttribute('aria-hidden', 'true')
}

let livewireNavigationPending = false

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
document.addEventListener('livewire:navigate', () => {
    livewireNavigationPending = true
    showPageLoading()
})
document.addEventListener('livewire:navigated', () => {
    livewireNavigationPending = false
    hidePageLoading()
})
document.addEventListener('livewire:initialized', () => {
    window.Livewire?.interceptMessage(({ onSend, onFinish }) => {
        onSend(showPageLoading)
        onFinish(() => {
            if (! livewireNavigationPending) hidePageLoading()
        })
    })
})
document.addEventListener('turbo:before-fetch-request', (event) => {
    const headers = event.detail?.fetchOptions?.headers

    if (headers && new Headers(headers).get('X-Sec-Purpose') === 'prefetch') return

    showPageLoading()
})
document.addEventListener('turbo:load', hidePageLoading)
document.addEventListener('turbo:frame-load', hidePageLoading)
document.addEventListener('turbo:fetch-request-error', hidePageLoading)
document.addEventListener('turbo:frame-missing', hidePageLoading)
document.addEventListener('DOMContentLoaded', hidePageLoading)

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

    if (wantsNativeVisit(link, url)) {
        return
    }

    const workspaceFrame = document.getElementById('workspace-shell')

    if (workspaceFrame && sameOriginPage(url)) {
        event.preventDefault()
        workspaceFrame.src = url.href

        return
    }

    if (! window.Livewire?.navigate) return

    event.preventDefault()
    window.Livewire.navigate(url.href)
})

document.addEventListener('submit', (event) => {
    const form = event.target

    if (! (form instanceof HTMLFormElement) || event.defaultPrevented || form.dataset.turbo === 'false') {
        return
    }

    if ((form.getAttribute('method') || 'get').toLowerCase() !== 'get') {
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

    const workspaceFrame = document.getElementById('workspace-shell')

    if (workspaceFrame && sameOriginPage(action)) {
        event.preventDefault()
        workspaceFrame.src = action.href

        return
    }

    if (! window.Livewire?.navigate) return

    event.preventDefault()
    window.Livewire.navigate(action.href)
})

document.addEventListener('turbo:frame-missing', (event) => {
    const response = event.detail?.response

    if (! response?.url || ! sameOriginPage(new URL(response.url, window.location.href))) return

    event.preventDefault()
    showPageLoading()
    window.location.assign(response.url)
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

    if (form.dataset.confirm && form.dataset.confirmed !== 'true') return

    showPageLoading()
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

function formatAcres(value) {
    if (!Number.isFinite(value) || value <= 0) {
        return ''
    }

    return String(value)
        .replace(/(\.\d*?[1-9])0+$/u, '$1')
        .replace(/\.0+$/u, '')
}

function indicativePurchaseTotal(form, acreage) {
    const basis = form.dataset.priceBasis || 'total'
    const rate = Number(form.dataset.priceRate)
    const amount = Number(form.dataset.priceAmount)
    const offered = Number(form.dataset.offeredAcreage)

    if (!Number.isFinite(acreage) || acreage <= 0) {
        return null
    }

    if (basis === 'per_acre' && Number.isFinite(rate) && rate > 0) {
        return Math.round(rate * acreage)
    }

    if (!Number.isFinite(amount) || amount <= 0 || !Number.isFinite(offered) || offered <= 0) {
        return null
    }

    return Math.round(amount * (acreage / offered))
}

function refreshPurchasePricing(form) {
    const acreageInput = form.querySelector('[data-purchase-acreage]')
    const output = form.querySelector('[data-purchase-indicative-price]')
    if (!output) {
        return
    }

    const available = Number(form.dataset.availableAcreage)
    const whole = form.dataset.wholeAcreage
    let acreage = whole !== '' ? Number(whole) : Number(acreageInput?.value)

    if ((!Number.isFinite(acreage) || acreage <= 0) && acreageInput && whole === '') {
        acreage = available
    }

    const total = indicativePurchaseTotal(form, acreage)
    const rate = Number(form.dataset.priceRate)
    const basis = form.dataset.priceBasis || 'total'

    if (total === null) {
        output.hidden = true
        output.textContent = ''

        return
    }

    const acresLabel = formatAcres(acreage)
    const parts = [
        `Selected: ${acresLabel} acres`,
        `Available: ${formatAcres(available)} acres`,
    ]

    if (basis === 'per_acre' && Number.isFinite(rate) && rate > 0) {
        parts.push(`Asking: KSh ${rate.toLocaleString()} per acre`)
    }

    parts.push(`Indicative asking total: KSh ${total.toLocaleString()} (estimate only, not agreed price)`)
    output.textContent = parts.join(' · ')
    output.hidden = false
}

function bindPurchasePricing(root = document) {
    root.querySelectorAll('form[data-purchase-pricing]').forEach((form) => {
        refreshPurchasePricing(form)
        const acreageInput = form.querySelector('[data-purchase-acreage]')
        acreageInput?.addEventListener('input', () => refreshPurchasePricing(form))
        acreageInput?.addEventListener('change', () => refreshPurchasePricing(form))
    })
}

document.addEventListener('DOMContentLoaded', () => bindPurchasePricing())
document.addEventListener('turbo:frame-load', (event) => {
    if (event.target instanceof HTMLElement) {
        bindPurchasePricing(event.target)
        if (event.target.id === 'sales-create-context') {
            applySalesCreateSuggestions(event.target.closest('form[data-sales-create]'), event.target)
        }
    }
})

function salesCreateParams(form) {
    const params = new URLSearchParams()
    const propertyId = form.querySelector('[name="property_id"]')?.value
    const buyerId = form.querySelector('[name="buyer_id"]')?.value
    const origin = form.querySelector('[name="origin"]')?.value
    if (propertyId) params.set('property_id', propertyId)
    if (buyerId) params.set('buyer_id', buyerId)
    if (origin) params.set('origin', origin)

    return params
}

function refreshSalesCreateContext(form) {
    const frame = form.querySelector('#sales-create-context')
    const url = form.dataset.contextUrl
    if (!(frame instanceof HTMLElement) || !url) return

    const query = salesCreateParams(form).toString()
    frame.src = query ? `${url}?${query}` : url
}

function applySalesCreateSuggestions(form, frameRoot) {
    if (!(form instanceof HTMLFormElement)) return

    const node = frameRoot?.querySelector('[data-sales-create-suggestions]')
    if (!(node instanceof HTMLScriptElement) || !node.textContent) return

    let suggestions = {}
    try {
        suggestions = JSON.parse(node.textContent)
    } catch {
        return
    }

    form.querySelectorAll('[data-sales-create-suggest]').forEach((field) => {
        const key = field.dataset.salesCreateSuggest
        const value = suggestions[key]
        if (value === null || value === undefined || value === '') return

        if (field instanceof HTMLSelectElement || field instanceof HTMLInputElement) {
            if (field.value !== '') return
            field.value = String(value)
        }
    })
}

function bindSalesCreateContext(root = document) {
    root.querySelectorAll('form[data-sales-create]').forEach((form) => {
        if (!(form instanceof HTMLFormElement) || form.dataset.salesCreateBound === 'true') return
        form.dataset.salesCreateBound = 'true'

        form.querySelectorAll('[data-sales-create-field]').forEach((field) => {
            field.addEventListener('change', () => refreshSalesCreateContext(form))
        })

        form.addEventListener('click', (event) => {
            const button = event.target.closest('[data-apply-offer]')
            if (!button || !form.contains(button)) return
            event.preventDefault()
            const origin = form.querySelector('[name="origin"]')
            if (origin instanceof HTMLSelectElement) {
                origin.value = 'offer'
            }
            refreshSalesCreateContext(form)
        })

        refreshSalesCreateContext(form)
    })
}

document.addEventListener('DOMContentLoaded', () => bindSalesCreateContext())
document.addEventListener('turbo:frame-load', (event) => {
    if (event.target instanceof HTMLElement) {
        bindSalesCreateContext(event.target)
    }
})

window.LamuAcres = {
    notify,
    confirm: confirmAction,
    clearModal: clearModalFrame,
}
