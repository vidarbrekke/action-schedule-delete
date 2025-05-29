import React from 'react';
import { CheckCircle } from 'lucide-react';
import { components, typography, colors } from '../styles/designSystem';

export const ParticipantControls: React.FC = () => (
  <div className="text-center">
    <h3 className={`${typography.heading.sm} mb-4`}>Player Status</h3>
    <div className={`${components.card.gradient} p-4 rounded-lg`}>
      <CheckCircle size={32} className="text-green-600 mx-auto mb-3" />
      <span
        className={`${components.badge.status} inline-flex items-center`}
        role="status"
        aria-label="Participant is ready"
      >
        <span className="w-2 h-2 mr-2 bg-green-400 rounded-full"></span>
        Ready to Play
      </span>
      <p className={`${typography.body.sm} ${colors.text.muted} mt-2`}>
        Waiting for the judge to start the game
      </p>
    </div>
  </div>
); 