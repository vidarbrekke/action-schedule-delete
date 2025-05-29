import { useState } from 'react';

export type PlayerState = 'IDLE' | 'BUZZED_IN' | 'ANSWERING' | 'TIMED_OUT' | 'ANSWERED';

export interface Answer {
  raw: string;
}

export interface PlayerRoundState {
  state: PlayerState;
  answer?: Answer;
}

export function usePlayerRoundState(initial: PlayerState = 'IDLE') {
  const [playerState, setPlayerState] = useState<PlayerRoundState>({ state: initial });

  const buzzIn = () => setPlayerState({ state: 'BUZZED_IN' });
  const startAnswering = (answer?: Answer) => setPlayerState({ state: 'ANSWERING', answer });
  const timeout = () => setPlayerState({ state: 'TIMED_OUT' });
  const answered = (answer?: Answer) => setPlayerState({ state: 'ANSWERED', answer });
  const reset = () => setPlayerState({ state: 'IDLE' });

  return { playerState, buzzIn, startAnswering, timeout, answered, reset, setPlayerState };
} 