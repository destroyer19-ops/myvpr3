// const appWorker = new Worker("http://localhost/static/js/1worker.js?v=2323");
// appWorker.postMessage("00");
// appWorker.onmessage = function (e) {
//   console.log("worker data ==>", e);
// };

class Ngage {
  constructor(config) {
    this.id = config?.id || null;
    this.userID = config?.userID || "default";
    this.anchor = config?.anchor || null;
    this.username = config?.username || "default";
    this.theme = config?.theme || "light";
    this.endpoint = config?.endpoint || "./Ngage/Ngage.php";
  }

  // bubble template
  tpl() {
    return `
    <div class="chatbox ${this.theme}">
                <div class="chat-wrapper">
                    <div class="chat-con" id="chatcon__${this.id}">

                    </div>
                    <form id="xxvxxvxxvxv"  autocomplete="off" class="bottom">
                        <div id="comment_box__${this.id}" class="fieldx flex align-items-center justify-content-end">
                            <input name="comment" id="sendr__${this.id}" placeholder="Join the conversation. . ." rows=2>
                            <div class="butt">
                                <button type="button" id="sendrBtn__${this.id}"class="flex">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" color="#fff" fill="none">
                                        <path d="M21.0477 3.05293C18.8697 0.707363 2.48648 6.4532 2.50001 8.551C2.51535 10.9299 8.89809 11.6617 10.6672 12.1581C11.7311 12.4565 12.016 12.7625 12.2613 13.8781C13.3723 18.9305 13.9301 21.4435 15.2014 21.4996C17.2278 21.5892 23.1733 5.342 21.0477 3.05293Z" stroke="currentColor" stroke-width="1.5" />
                                        <path d="M11.5 12.5L15 9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>
                            </div>

                        </div>
                    </form>
                </div>
            </div>
    `;
  }

  // submit chat
  async send(val) {
    this.load.start();

    let res = await fetch(`${this.endpoint}?do=send`, {
      method: "post",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        name: this.username,
        id: this.id,
        xid: this.userID,
        comment: val,
      }),
    });

    res.status;
    const r = await res.json();
    if (r.status) {
      this.load.stop();
      this.iField.value = "";
      this.entry([
        {
          name: this.username,
          time: new Date().toLocaleString("en-US", {
            hour: "numeric",
            minute: "numeric",
            hour12: true,
          }),
          today: false,
          date: "",
          comment: val,
        },
      ]);
      showToast("Comment posted successfully!");
    } else {
      this.load.stop();
      showToast(r.msg || "Failed to post comment", false);
    }
  }

  //btn loader
  load = {
    start: () => {
      this.ibtn.innerHTML = "<span class='Nloadr'></span>";
    },
    stop: () => {
      this.ibtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" color="#fff" fill="none">
                                        <path d="M21.0477 3.05293C18.8697 0.707363 2.48648 6.4532 2.50001 8.551C2.51535 10.9299 8.89809 11.6617 10.6672 12.1581C11.7311 12.4565 12.016 12.7625 12.2613 13.8781C13.3723 18.9305 13.9301 21.4435 15.2014 21.4996C17.2278 21.5892 23.1733 5.342 21.0477 3.05293Z" stroke="currentColor" stroke-width="1.5" />
                                        <path d="M11.5 12.5L15 9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>`;
    },
  };

  // Create comment
  async entry(comments) {
    comments.forEach((comment) => {
      let bubbleWrap = document.createElement("div");
      bubbleWrap.innerHTML = `<div class="bubble animate__animated animate__heartbeat ">
                <div>
                        <img src="https://api.dicebear.com/9.x/initials/svg?seed=${
                          comment.name
                        }" alt="matdash-img" class="rounded-circle" style="width:30px; height:30px; max-width:30px">
                </div>
              
                <div style="width:100%; margin-top:-5px; position:relative">
                      <span class="nem">${comment.name}</span>
                      <span class="tim">${
                        new Date(comment.date * 1000).toLocaleDateString() ==
                        new Date().toLocaleDateString()
                          ? ""
                          : Intl.DateTimeFormat("en", {
                              day: "2-digit",
                              month: "short",
                            }).format(new Date(comment.date * 1000)) + ", "
                      } ${comment.time}</span>
                      <span class="txt">${comment.comment}</span>
                </div>
              </div>`;
      document.querySelector(`#chatcon__${this.id}`).append(bubbleWrap);
    });
    let ct = document.querySelector(`#chatcon__${this.id}`);
    ct.scrollTo(0, ct.scrollHeight);
  }

  async init() {
    try {
      //Build elements
      let anchor = document.querySelector(this.anchor);
      anchor.classList += ` ${this.theme}`;
      anchor.innerHTML = this.tpl();

      // hide comment box if no user
      if (this.userID == "default" || this.username == "default") {
        document.querySelector(`#comment_box__${this.id}`).style.display =
          "none";
      }

      let x = await fetch(`${this.endpoint}?get=${this.id}`, {
        method: "post",
      });

      let comments = await x.json();
      comments.length > 0
        ? this.entry(comments)
        : this.entry([
            {
              name: "Comment Bot",
              time: new Date().toLocaleString("en-US", {
                hour: "numeric",
                minute: "numeric",
                hour12: true,
              }),
              today: false,
              date: "",
              comment: `Hey there 👋, Be the first to use the comment box by typing and hitting enter below. lets get started `,
            },
          ]);

      //Connect with sse
      var source = new EventSource(
        `${this.endpoint}?fetch=${this.id}&uid=${this.userID}`
      );
      const G = this;
      source.onmessage = function (event) {
        let data = event.data;
        let jdata = JSON.parse(data);
        jdata.length > 0 ? G.entry(jdata) : null;
      };

      //Handle Sbmit

      document.querySelector("#xxvxxvxxvxv").addEventListener("submit", (e) => {
        e.preventDefault();
      });
      this.iField = document.querySelector(`#sendr__${this.id}`);
      this.ibtn = document.querySelector(`#sendrBtn__${this.id}`);

      this.iField.addEventListener("keydown", (e) => {
        if (e.code === "Enter") {
          e.preventDefault();
          this.send(this.iField.value);
        }
      });

      this.ibtn.addEventListener("click", (e) => {
        this.send(this.iField.value);
      });
    } catch (error) {
      console.error("modal error", error);
      alert("Something went wrong while loading chats");
    }
  }
}

// let con = document.getElementById("chatcon");
// con.addEventListener("scrollend", (e) => {
//   setTimeout(() => {
//     con.style.overflowY = "hidden";
//   }, 3000);
// });

// con.addEventListener("mouseover", () => {
//   con.style.overflowY = "scroll";
// });

// con.addEventListener("mouseleave", () => {
//   setTimeout(() => {
//     con.style.overflowY = "hidden";
//   }, 3000);
// });
