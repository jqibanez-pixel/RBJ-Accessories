// @ts-nocheck

/* ================= GLOBAL STATE ================= */
let customization = {
  motor: null,
  seatColor: null
};

/* ================= STEP 1: MOTOR SELECTION ================= */
const motorContainer = document.getElementById("motor-viewer");
const motorScene = new THREE.Scene();
motorScene.background = new THREE.Color(0x1a1a1a);
const motorCamera = new THREE.PerspectiveCamera(45,motorContainer.clientWidth/motorContainer.clientHeight,0.1,1000);
motorCamera.position.set(0,2.2,6);
const motorRenderer = new THREE.WebGLRenderer({antialias:true});
motorRenderer.setSize(motorContainer.clientWidth,motorContainer.clientHeight);
motorRenderer.outputEncoding = THREE.sRGBEncoding;
motorRenderer.physicallyCorrectLights = true;
motorContainer.appendChild(motorRenderer.domElement);
motorScene.add(new THREE.AmbientLight(0xffffff,1.1));
const motorLight1 = new THREE.DirectionalLight(0xffffff,2.2); motorLight1.position.set(8,10,8); motorScene.add(motorLight1);
const motorLight2 = new THREE.DirectionalLight(0xffffff,1.5); motorLight2.position.set(-8,5,5); motorScene.add(motorLight2);
const motorControls = new THREE.OrbitControls(motorCamera,motorRenderer.domElement);

let motorModel = null;
function loadMotor(modelName){
  if(motorModel) motorScene.remove(motorModel);
  const loader = new THREE.GLTFLoader();
  loader.load(`../aerox.glb`,(gltf)=>{
    motorModel = gltf.scene;
    motorModel.scale.set(1.5,1.5,1.5);
    motorScene.add(motorModel);
  });
}

function selectMotor(type){
  customization.motor = type;
  loadMotor(type);
  // Move to seat customization after small delay
  setTimeout(()=>{
    document.getElementById("motor-selection").style.display="none";
    document.getElementById("seat-customization").style.display="block";
    initSeatCustomization();
  },1000);
}

function animateMotor(){ requestAnimationFrame(animateMotor); motorControls.update(); motorRenderer.render(motorScene,motorCamera);}
animateMotor();
window.addEventListener("resize",()=>{ motorCamera.aspect=motorContainer.clientWidth/motorContainer.clientHeight; motorCamera.updateProjectionMatrix(); motorRenderer.setSize(motorContainer.clientWidth,motorContainer.clientHeight); });

/* ================= STEP 2: SEAT CUSTOMIZATION ================= */
const seatContainer = document.getElementById("seat-viewer");
const seatScene = new THREE.Scene();
seatScene.background = new THREE.Color(0x1a1a1a);
const seatCamera = new THREE.PerspectiveCamera(45,seatContainer.clientWidth/seatContainer.clientHeight,0.1,1000);
seatCamera.position.set(0,2.2,6);
const seatRenderer = new THREE.WebGLRenderer({antialias:true});
seatRenderer.setSize(seatContainer.clientWidth,seatContainer.clientHeight);
seatRenderer.outputEncoding = THREE.sRGBEncoding;
seatRenderer.physicallyCorrectLights = true;
seatContainer.appendChild(seatRenderer.domElement);
seatScene.add(new THREE.AmbientLight(0xffffff,1.1));
const seatKeyLight = new THREE.DirectionalLight(0xffffff,2.2); seatKeyLight.position.set(8,10,8); seatScene.add(seatKeyLight);
const seatFillLight = new THREE.DirectionalLight(0xffffff,1.5); seatFillLight.position.set(-8,5,5); seatScene.add(seatFillLight);
const seatControls = new THREE.OrbitControls(seatCamera,seatRenderer.domElement);

let seatMesh = null;
let baseSeatMaterial = null;

function initSeatCustomization(){
  const loader = new THREE.GLTFLoader();
  loader.load("../aerox.glb",(gltf)=>{
    const model = gltf.scene;
    model.traverse(obj=>{
      if(!obj.isMesh) return;
      obj.material.side=THREE.DoubleSide;
      if(obj.name === "seat_aerox"){
        seatMesh = obj;
        baseSeatMaterial = obj.material.clone();
      }
    });
    model.scale.set(1.5,1.5,1.5);
    seatScene.add(model);
  });
}

function changeSeat(color){
  customization.seatColor=color;
  if(!seatMesh || !baseSeatMaterial) return;
  const newMat = baseSeatMaterial.clone();
  if(color === 'red'){
    newMat.color.setHex(0xff0000);
  } else if(color === 'black'){
    newMat.color.setHex(0x000000);
  } else if(color === 'brown'){
    newMat.color.setHex(0x8B4513);
  }
  newMat.needsUpdate = true;
  seatMesh.material = newMat;
}

function animateSeat(){ requestAnimationFrame(animateSeat); seatControls.update(); seatRenderer.render(seatScene,seatCamera);}
animateSeat();
window.addEventListener("resize",()=>{ seatCamera.aspect=seatContainer.clientWidth/seatContainer.clientHeight; seatCamera.updateProjectionMatrix(); seatRenderer.setSize(seatContainer.clientWidth,seatContainer.clientHeight); });

function confirmSeat(){
  // Go to preview
  document.getElementById("seat-customization").style.display="none";
  document.getElementById("preview-section").style.display="block";
  showPreview();
}

/* ================= STEP 3: PREVIEW + AI ================= */
function showPreview(){
  const previewContainer=document.getElementById("preview-viewer");
  const previewScene=new THREE.Scene();
  previewScene.background=new THREE.Color(0x1a1a1a);
  const previewCamera=new THREE.PerspectiveCamera(45,previewContainer.clientWidth/previewContainer.clientHeight,0.1,1000);
  previewCamera.position.set(0,2.2,6);
  const previewRenderer=new THREE.WebGLRenderer({antialias:true});
  previewRenderer.setSize(previewContainer.clientWidth,previewContainer.clientHeight);
  previewRenderer.outputEncoding=THREE.sRGBEncoding;
  previewRenderer.physicallyCorrectLights=true;
  previewContainer.appendChild(previewRenderer.domElement);
  previewScene.add(new THREE.AmbientLight(0xffffff,1.1));
  const keyL=new THREE.DirectionalLight(0xffffff,2.2); keyL.position.set(8,10,8); previewScene.add(keyL);
  const fillL=new THREE.DirectionalLight(0xffffff,1.5); fillL.position.set(-8,5,5); previewScene.add(fillL);
  const controls=new THREE.OrbitControls(previewCamera,previewRenderer.domElement);

  // Load selected motor model
  const loader=new THREE.GLTFLoader();
  loader.load("../aerox.glb",(gltf)=>{
    const model=gltf.scene;
    model.traverse(obj=>{
      if(!obj.isMesh) return;
      // Apply selected seat color if seat exists
      if(customization.seatColor && obj.name === "seat_aerox"){
        const newMat = obj.material.clone();
        if(customization.seatColor === 'red'){
          newMat.color.setHex(0xff0000);
        } else if(customization.seatColor === 'black'){
          newMat.color.setHex(0x000000);
        } else if(customization.seatColor === 'brown'){
          newMat.color.setHex(0x8B4513);
        }
        newMat.needsUpdate = true;
        obj.material = newMat;
      }
    });
    model.scale.set(1.5,1.5,1.5);
    previewScene.add(model);

    // Mock AI recommendation
    const aiText=document.getElementById("ai-suggestion");
    if(customization.seatColor==="black"){
      aiText.textContent="AI Suggestion: Red seat may enhance style and visibility.";
    }else if(customization.seatColor==="brown"){
      aiText.textContent="AI Suggestion: Black seat gives a sportier look.";
    }else{
      aiText.textContent="AI Suggestion: Your selection looks great!";
    }
  });

  function animatePreview(){ requestAnimationFrame(animatePreview); controls.update(); previewRenderer.render(previewScene,previewCamera);}
  animatePreview();

  window.addEventListener("resize",()=>{ previewCamera.aspect=previewContainer.clientWidth/previewContainer.clientHeight; previewCamera.updateProjectionMatrix(); previewRenderer.setSize(previewContainer.clientWidth,previewContainer.clientHeight); });
}

function finalConfirm(){
  const params = new URLSearchParams({
    motor: customization.motor || 'aerox',
    seatColor: customization.seatColor || 'black',
    material: 'leather',
    stitch: 'straight',
    seatType: 'standard',
    design: 'plain'
  }).toString();
  window.location.href = 'customize.php?step=preview&' + params;
}

const accountDropdown = document.querySelector('.account-dropdown');
const accountTrigger = document.querySelector('.account-trigger');
const accountMenu = document.querySelector('.account-menu');

accountTrigger.addEventListener('click', function (e) {
  e.stopPropagation();
  accountDropdown.classList.toggle('active');
});

accountMenu.addEventListener('click', function (e) {
  e.stopPropagation();
});

document.addEventListener('click', function () {
  accountDropdown.classList.remove('active');
});

