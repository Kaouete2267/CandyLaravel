{{--
    Sur mobile, ajoute un bouton « Prendre une photo » sous chaque champ d'upload (FilePond) acceptant des
    images : selon le navigateur, le sélecteur de fichiers standard ne propose pas toujours l'appareil photo.
    Le fichier capturé est injecté dans l'input natif de FilePond, qui le traite comme un fichier choisi.
--}}
<template id="camera-capture-button">
    <div class="camera-capture" style="margin-top: 0.5rem">
        <x-filament::button color="gray" outlined size="sm" icon="heroicon-o-camera" type="button">
            Prendre une photo
        </x-filament::button>
    </div>
</template>

<script>
    (() => {
        if (! window.matchMedia('(pointer: coarse)').matches || typeof DataTransfer === 'undefined') {
            return;
        }

        const template = document.getElementById('camera-capture-button');

        const enhance = (root) => {
            const browser = root.querySelector('input.filepond--browser');
            const accept = browser?.getAttribute('accept') ?? '';

            if (! browser || root.dataset.cameraCapture || (accept !== '' && ! accept.includes('image'))) {
                return;
            }

            root.dataset.cameraCapture = '1';

            const wrapper = template.content.firstElementChild.cloneNode(true);
            const camera = document.createElement('input');
            camera.type = 'file';
            camera.accept = 'image/*';
            camera.capture = 'environment';
            camera.hidden = true;
            wrapper.append(camera);

            wrapper.querySelector('button').addEventListener('click', () => {
                if (! browser.disabled) {
                    camera.click();
                }
            });

            camera.addEventListener('change', () => {
                if (! camera.files.length) {
                    return;
                }

                const transfer = new DataTransfer();
                Array.from(camera.files).forEach((file) => transfer.items.add(file));
                browser.files = transfer.files;
                browser.dispatchEvent(new Event('change'));
                camera.value = '';
            });

            root.after(wrapper);
        };

        const scan = () => document.querySelectorAll('.filepond--root').forEach(enhance);

        new MutationObserver(scan).observe(document.body, { childList: true, subtree: true });
        scan();
    })();
</script>
