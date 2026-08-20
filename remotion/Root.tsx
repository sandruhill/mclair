import { Composition } from 'remotion';
import { PainelFlow } from './PainelFlow';

export const Root: React.FC = () => {
  return (
    <>
      <Composition
        id="PainelFlow"
        component={PainelFlow}
        durationInFrames={210}
        fps={30}
        width={1280}
        height={800}
      />
    </>
  );
};
