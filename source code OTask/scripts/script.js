function showTextEffect(select,speed) {
    let div = document.querySelector(select);

    let all_h2 = div.querySelectorAll("h2");
    let temph2=[];
    all_h2.forEach(element => {
        temph2.push(element.textContent)
        element.textContent="";
    });


    let indexh2=0;
    let index=0;
    let play=true;
    const intervalId = setInterval(() => {
        if (play){
            if (index>temph2[indexh2].length-1){
                indexh2+=1
                index=0
            }
            if(indexh2>all_h2.length-1){
                play=false;
                setTimeout(() => {
                    clearInterval(intervalId);
                    showTextEffect(select, speed);
                }, 3000);
            }else{
                all_h2[indexh2].textContent+=temph2[indexh2][index];
                index+=1;
            }
        } 
    }, speed);
}

