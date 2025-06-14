
showTextEffect("main .message",100);


const passwordInput = document.getElementById("password");
const toggleIcon= document.querySelector("#togglePassword");
if(toggleIcon){
    toggleIcon.addEventListener("click", () => {
        const isPassword = passwordInput.type === "password";
        if(isPassword){
            passwordInput.type ="text";
            toggleIcon.src="../imgs/Eye.png";
        }else{
            passwordInput.type ="password";
            toggleIcon.src="../imgs/Hide.png";
        }
    });
    
}

const forget=document.querySelector(".foget");
const password =document.querySelector(".input-password");
const label_pss=document.getElementById("label_pss");
const login= document.getElementById("login");
const back=document.getElementById("back");
if(forget){
    forget.addEventListener("click", () => {
        password.style.display="none";
        forget.style.display="none";
        label_pss.style.display="none";
        login.textContent="Send code";
        login.setAttribute("name","send");
        back.style.display="block";
    });
}
if(document.getElementById("time_scondes")){
    const span=document.getElementById("time_scondes")

    const intervalId = setInterval(() => {
        if (span.textContent>0){
            span.textContent-=1;
        }else{
            span.textContent=0;
            span.style.display="none"
            clearInterval(intervalId);
        }
    }, 1000);
}