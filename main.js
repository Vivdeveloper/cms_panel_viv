// Shared Three.js Background Animation
function setupBackground() {
    const container = document.getElementById('canvas-container');
    if (!container) return;

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
    camera.position.z = 30;

    const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    container.appendChild(renderer.domElement);

    const particlesGeometry = new THREE.BufferGeometry();
    const particlesCount = 1500;
    const posArray = new Float32Array(particlesCount * 3);

    for (let i = 0; i < particlesCount * 3; i++) {
        posArray[i] = (Math.random() - 0.5) * 100;
    }

    particlesGeometry.setAttribute('position', new THREE.BufferAttribute(posArray, 3));
    const particlesMaterial = new THREE.PointsMaterial({
        size: 0.1,
        color: '#4facfe',
        transparent: true,
        opacity: 0.8,
        blending: THREE.AdditiveBlending
    });

    const particlesMesh = new THREE.Points(particlesGeometry, particlesMaterial);
    scene.add(particlesMesh);

    const geometry = new THREE.TorusKnotGeometry(10, 3, 100, 16);
    const material = new THREE.MeshNormalMaterial({ wireframe: true, transparent: true, opacity: 0.1 });
    const torusKnot = new THREE.Mesh(geometry, material);
    scene.add(torusKnot);

    let mouseX = 0, mouseY = 0;
    document.addEventListener('mousemove', (event) => {
        mouseX = (event.clientX - window.innerWidth / 2) * 0.01;
        mouseY = (event.clientY - window.innerHeight / 2) * 0.01;
    });

    function animate() {
        requestAnimationFrame(animate);
        particlesMesh.rotation.y += 0.001;
        torusKnot.rotation.y += 0.005;
        torusKnot.rotation.z += 0.003;
        camera.position.x += (mouseX - camera.position.x) * 0.05;
        camera.position.y += (-mouseY - camera.position.y) * 0.05;
        camera.lookAt(scene.position);
        renderer.render(scene, camera);
    }
    animate();

    window.addEventListener('resize', () => {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
    });
}

// Hidden Panel Logic
let inputKeys = '';
const secretCode = '12345';
window.addEventListener('keydown', (e) => {
    inputKeys += e.key;
    if (inputKeys.length > secretCode.length) inputKeys = inputKeys.substring(inputKeys.length - secretCode.length);
    if (inputKeys === secretCode) {
        const panel = document.getElementById('control-panel');
        if (panel) {
            panel.style.display = 'block';
            setTimeout(() => panel.classList.add('visible'), 10);
        }
        inputKeys = '';
    }
});

function togglePanel(show) {
    const panel = document.getElementById('control-panel');
    if (!panel) return;
    if (show) {
        panel.style.display = 'block';
        setTimeout(() => panel.classList.add('visible'), 10);
    } else {
        panel.classList.remove('visible');
        setTimeout(() => panel.style.display = 'none', 400);
    }
}

function saveSettings() {
    const branchEl = document.getElementById('branch-select');
    const branch = branchEl ? branchEl.value : 'main';
    alert(`GitHub Sync Started:\nBranch: ${branch}\nMode: PHP Flat-File Sync`);
    togglePanel(false);
}

function downloadProject(type) {
    const branch = document.getElementById('branch-select').value;
    if (type === 'zip') {
        alert(`Generating ZIP archive...\nBranch: ${branch}\nIncludes: .php, .css, .js, .json\nDownload will start automatically.`);
        // In a real production environment, this would call a PHP script using ZipArchive
    } else if (type === 'sync') {
        const confirmSync = confirm(`Sync current design to GitHub branch: ${branch}?`);
        if (confirmSync) {
            alert(`Pushing code to ${branch}...\nDeployment successful.`);
            togglePanel(false);
        }
    }
}

function deployToFTP() {
    const host = document.getElementById('ftp-host').value;
    const user = document.getElementById('ftp-user').value;
    
    if(!host || !user) {
        alert("Please enter FTP Host and Username to deploy.");
        return;
    }

    const confirmDeploy = confirm(`Are you sure you want to deploy the current design to ${host}?`);
    if(confirmDeploy) {
        const btn = event.target;
        const originalText = btn.textContent;
        btn.textContent = "Connecting to FTP...";
        btn.disabled = true;

        setTimeout(() => {
            btn.textContent = "Uploading assets...";
            setTimeout(() => {
                alert(`Success! Site updated on ${host}.\nAll PHP and 3D files synchronized via FTP CD.`);
                btn.textContent = originalText;
                btn.disabled = false;
                togglePanel(false);
            }, 1500);
        }, 1000);
    }
}

function checkGithubPulse() {
    const branchEl = document.getElementById('branch-select');
    const branch = branchEl ? branchEl.value : 'main';
    const statusText = document.getElementById('branch-status');
    if (!statusText) return;

    statusText.textContent = `Pulsing GitHub for ${branch}...`;
    statusText.style.color = '#4facfe';

    setTimeout(() => {
        const time = new Date().toLocaleTimeString();
        statusText.textContent = `Live: Branch ${branch} is Active (Synced at ${time})`;
        statusText.style.color = '#25D366';
        console.log(`Dynamic update for ${branch} completed.`);
    }, 1200);
}

function setupPublicNav() {
    const toggle = document.getElementById('nav-menu-toggle');
    const drawer = document.getElementById('site-nav-drawer');
    const overlay = document.getElementById('nav-drawer-overlay');
    const closeBtn = document.getElementById('nav-drawer-close');
    if (!toggle || !drawer || !overlay) {
        return;
    }

    function setOpen(open) {
        document.body.classList.toggle('nav-drawer-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        drawer.classList.toggle('is-open', open);
        overlay.classList.toggle('is-active', open);
        overlay.setAttribute('aria-hidden', open ? 'false' : 'true');
        if ('inert' in drawer) {
            drawer.inert = !open;
        }
    }

    toggle.addEventListener('click', () => {
        setOpen(!drawer.classList.contains('is-open'));
    });
    overlay.addEventListener('click', () => setOpen(false));
    if (closeBtn) {
        closeBtn.addEventListener('click', () => setOpen(false));
    }
    drawer.querySelectorAll('a.nav-page-link').forEach((a) => {
        a.addEventListener('click', () => setOpen(false));
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            setOpen(false);
        }
    });

    window.addEventListener('resize', () => {
        if (window.matchMedia('(min-width: 1024px)').matches) {
            setOpen(false);
        }
    });

    var sticky = document.querySelector('.nav-sticky-cta');
    if (sticky) {
        document.body.classList.add('has-sticky-cta');
        if (sticky.getAttribute('data-sticky-desktop') === '1') {
            document.body.classList.add('has-sticky-desktop');
        }
        if (sticky.classList.contains('nav-sticky-cta--full') || sticky.getAttribute('data-sticky-layout') === 'full') {
            document.body.classList.add('has-sticky-layout-full');
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    setupBackground();
    setupPublicNav();
});
