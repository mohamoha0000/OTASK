
showTextEffect("main .message",100);


const passwordInput = document.getElementById("password");
const passwordInput2 = document.getElementById("confirm_pass");
const toggleIcons= document.querySelectorAll("#togglePassword")
toggleIcons.forEach(toggleIcon => {
    toggleIcon.addEventListener("click", () => {
        const isPassword = passwordInput.type === "password";
        if(isPassword){
            passwordInput.type ="text";
            passwordInput2.type = "text";

            toggleIcons.forEach(tog =>{
                tog.src="../imgs/Eye.png"
            });
        }else{
            passwordInput.type ="password";
            passwordInput2.type = "password";
            toggleIcons.forEach(tog =>{
                tog.src="../imgs/Hide.png"
            });
        }
    }); 
});



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