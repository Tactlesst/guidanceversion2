<?php
// Existing questionnaires from guidance/surveys; wording and scoring groups preserved.
return json_decode(<<<'QUESTIONS'
{
  "ls": [
    "I like to read about something rather than hear about it.",
    "I remember best by writing things down or drawing pictures.",
    "I need to see pictures and videos to help me learn.",
    "I need to write it down so I won't forget it.",
    "I read better when I read aloud.",
    "I would rather listen to a story than read it myself.",
    "I can easily remember what people say.",
    "I would rather tell the answer than write it down.",
    "If I have to solve a problem, it helps me to move while I think.",
    "I enjoy dancing or moving to music.",
    "It helps me if I can move around when I work.",
    "I like it when teachers let me practice something with an activity."
  ],
  "ls_intermediate": [
    "I prefer to see information written on the board and supplemented by visual aids and assigned readings.",
    "I remember something better if I write it down.",
    "I can easily understand and follow directions on a map.",
    "When someone tells me someone's telephone number, or something new like that, it helps me to get a picture of it in my mind.",
    "I find something to watch if I am bored.",
    "It helps me to look at the person while listening; it keeps me focused.",
    "Using flashcards helps me to retain material for tests.",
    "It's hard for me to understand what a person is saying when there are people talking or music playing.",
    "I am good at working and solving jigsaw puzzles and mazes.",
    "I prefer obtaining information about an interesting subject by reading about it.",
    "I learn to spell better by repeating words out loud than by writing the words on paper.",
    "I can remember things by listening to a lecture that includes information, explanations and discussions.",
    "Papers with very small print, blotchy dittos or poor copies are tough on me.",
    "I understand how to do something if someone tells me, rather than having to read the same thing to myself.",
    "I remember things that I hear, rather than things that I see or read.",
    "When I am trying, I press down hard on my pen or pencil.",
    "I would rather listen to a good lecture or speech than read about the same material.",
    "I prefer learning to the news on the radio or online rather than reading about it in a newspaper or on the internet.",
    "It's hard for me to read other people's handwriting.",
    "If I had the choice to learn new information through a lecture or textbook, I would choose to hear it rather than read it.",
    "I don't like to read directions; I'd rather just start doing.",
    "I learn best when I am shown how to do something, and I have the opportunity to do it.",
    "I enjoy working with my hands or making things.",
    "I tend to solve problems through a more trial-and-error approach, rather than from a step-by-step method.",
    "Before directions, it helps me to see someone else do it first.",
    "I find myself needing frequent breaks while studying.",
    "I am not skilled in giving verbal explanations or directions.",
    "I have trouble sitting still for any length of time.",
    "I think better when I have the freedom to move around.",
    "If I have to memorize something I write it out a number of times until I know it."
  ]
}
QUESTIONS, true, 512, JSON_THROW_ON_ERROR);
