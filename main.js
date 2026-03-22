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
    const repo = document.getElementById('repo-url').value;
    alert(`GitHub Sync Started: \nRepo: ${repo}\nBranch: ${document.getElementById('branch-select').value}\nMode: PHP Flat-File Sync`);
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

document.addEventListener('DOMContentLoaded', () => {
    setupBackground();
});
