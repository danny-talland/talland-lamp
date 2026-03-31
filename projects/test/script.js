const questions = [
  {
    question: "Welke planeet staat bekend als de rode planeet?",
    answers: ["Mars", "Venus", "Jupiter", "Mercurius"],
    correctIndex: 0
  },
  {
    question: "Hoeveel minuten zitten er in 2 uur?",
    answers: ["90", "100", "120", "140"],
    correctIndex: 2
  },
  {
    question: "Wat is de hoofdstad van Nederland?",
    answers: ["Rotterdam", "Utrecht", "Amsterdam", "Den Haag"],
    correctIndex: 2
  },
  {
    question: "Welk dier zegt 'miauw'?",
    answers: ["Hond", "Kat", "Koe", "Paard"],
    correctIndex: 1
  },
  {
    question: "Welke kleur krijg je door blauw en geel te mengen?",
    answers: ["Groen", "Paars", "Oranje", "Rood"],
    correctIndex: 0
  }
];

const quizElement = document.getElementById("quiz");
const resultElement = document.getElementById("result");
const statusElement = document.getElementById("status");
const questionElement = document.getElementById("question");
const answersElement = document.getElementById("answers");
const nextButton = document.getElementById("next-button");
const restartButton = document.getElementById("restart-button");
const scoreText = document.getElementById("score-text");

let currentQuestionIndex = 0;
let score = 0;
let hasAnswered = false;

function renderQuestion() {
  const currentQuestion = questions[currentQuestionIndex];
  hasAnswered = false;

  statusElement.textContent = `Vraag ${currentQuestionIndex + 1} van ${questions.length}`;
  questionElement.textContent = currentQuestion.question;
  answersElement.innerHTML = "";
  nextButton.disabled = true;

  currentQuestion.answers.forEach((answer, index) => {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "answer";
    button.textContent = answer;
    button.addEventListener("click", () => selectAnswer(button, index));
    answersElement.appendChild(button);
  });
}

function selectAnswer(selectedButton, selectedIndex) {
  if (hasAnswered) {
    return;
  }

  const currentQuestion = questions[currentQuestionIndex];
  const answerButtons = Array.from(document.querySelectorAll(".answer"));
  hasAnswered = true;

  answerButtons.forEach((button, index) => {
    button.disabled = true;

    if (index === currentQuestion.correctIndex) {
      button.classList.add("correct");
    }
  });

  if (selectedIndex === currentQuestion.correctIndex) {
    score += 1;
  } else {
    selectedButton.classList.add("wrong");
  }

  nextButton.disabled = false;
  nextButton.textContent = currentQuestionIndex === questions.length - 1 ? "Bekijk score" : "Volgende vraag";
}

function showResult() {
  quizElement.classList.add("hidden");
  resultElement.classList.remove("hidden");
  scoreText.textContent = `Je hebt ${score} van de ${questions.length} vragen goed beantwoord.`;
}

function restartQuiz() {
  currentQuestionIndex = 0;
  score = 0;
  nextButton.textContent = "Volgende vraag";
  resultElement.classList.add("hidden");
  quizElement.classList.remove("hidden");
  renderQuestion();
}

nextButton.addEventListener("click", () => {
  currentQuestionIndex += 1;

  if (currentQuestionIndex < questions.length) {
    renderQuestion();
    return;
  }

  showResult();
});

restartButton.addEventListener("click", restartQuiz);

renderQuestion();
